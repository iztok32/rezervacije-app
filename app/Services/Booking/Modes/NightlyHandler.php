<?php

namespace App\Services\Booking\Modes;

use App\Enums\ResourceSelection;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\User;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingDraft;
use App\Services\Booking\BookingException;
use Illuminate\Support\Carbon;

/**
 * Dnevi / nočitve: rezervacija vira za obdobje od datuma do datuma.
 *  - enota "night" (nastanitve): prihod na dan A ob check_in, odhod na dan B ob check_out,
 *    obračun B − A noči;
 *  - enota "day" (najem): prevzem na dan A ob check_in, vračilo na dan B ob check_out,
 *    obračun B − A + 1 dni.
 */
class NightlyHandler implements BookingModeHandler
{
    public function __construct(private Availability $availability) {}

    public function rules(ResourceType $type): array
    {
        return [
            'resource_id' => 'nullable|integer',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'quantity' => 'nullable|integer|min:1|max:999',
        ];
    }

    public function availabilityRules(): array
    {
        return [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
        ];
    }

    public static function checkIn(ResourceType $type): string
    {
        return substr($type->check_in_time ?: ($type->countsDays() ? '08:00' : '15:00'), 0, 5);
    }

    public static function checkOut(ResourceType $type): string
    {
        return substr($type->check_out_time ?: ($type->countsDays() ? '18:00' : '10:00'), 0, 5);
    }

    public function availability(ResourceType $type, array $query, ?User $viewer): array
    {
        $days = $this->availability->days(Carbon::parse($query['from']), Carbon::parse($query['to']));
        $resources = $this->availability->eligibleResources($type);
        $ids = $resources->pluck('id')->all();

        $result = [];
        if ($days) {
            $from = $days[0]->copy();
            $to = end($days)->copy()->addDays(2);
            $bookings = $this->availability->bookings($ids, $from, $to);
            $closures = $this->availability->closures($type, $ids, $from, $to);

            foreach ($resources as $resource) {
                $blocked = [];
                foreach ($days as $day) {
                    [$a, $b] = $this->unitInterval($type, $day);
                    $free = $this->availability->withinAdvanceWindow($type, $a)
                        && ! $this->availability->isClosed($closures, $resource->id, $a, $b)
                        && $this->availability->hasRoom($bookings, $resource, $a, $b);
                    if (! $free) {
                        $blocked[] = $day->toDateString();
                    }
                }
                $result[] = $this->resourcePayload($resource) + ['blocked' => $blocked];
            }
        }

        return [
            'unit' => $type->countsDays() ? 'day' : 'night',
            'check_in' => self::checkIn($type),
            'check_out' => self::checkOut($type),
            'min_units' => max(1, $type->min_units),
            'max_units' => $type->max_units,
            'resources' => $result,
        ];
    }

    public function draft(ResourceType $type, array $input, bool $privileged, ?Booking $ignore = null): BookingDraft
    {
        $startDate = Carbon::parse($input['start_date'])->startOfDay();
        $endDate = Carbon::parse($input['end_date'])->startOfDay();
        $units = $type->countsDays()
            ? (int) $startDate->diffInDays($endDate) + 1
            : (int) $startDate->diffInDays($endDate);

        if ($units < 1) {
            throw new BookingException('end_date', __('The departure date must be after the arrival date.'));
        }
        if ($units < max(1, $type->min_units)) {
            throw new BookingException('end_date', $type->countsDays()
                ? __('The minimum rental is :count days.', ['count' => $type->min_units])
                : __('The minimum stay is :count nights.', ['count' => $type->min_units]));
        }
        if ($type->max_units && $units > $type->max_units) {
            throw new BookingException('end_date', $type->countsDays()
                ? __('The maximum rental is :count days.', ['count' => $type->max_units])
                : __('The maximum stay is :count nights.', ['count' => $type->max_units]));
        }

        $start = Carbon::parse($startDate->toDateString().' '.self::checkIn($type));
        $end = Carbon::parse($endDate->toDateString().' '.self::checkOut($type));

        // Dnevni najem z vračilom ob uri prevzema ali prej (npr. 8:00 → 8:00): vračilo naslednji dan
        if ($type->countsDays() && self::checkOut($type) <= self::checkIn($type)) {
            $end->addDay();
        }

        if ($end->lte($start)) {
            throw new BookingException('end_date', __('The departure date must be after the arrival date.'));
        }
        if (! $privileged && ($error = $this->availability->advanceWindowError($type, $start))) {
            throw new BookingException('start_date', $error);
        }

        $requested = $input['resource_id'] ?? null;
        if (! $requested && $type->resource_selection === ResourceSelection::Customer) {
            throw new BookingException('resource_id', __('Please choose: :resource.', ['resource' => mb_strtolower($type->resourceLabel())]));
        }

        $quantity = (int) ($input['quantity'] ?? 1);
        $candidates = $this->availability->eligibleResources($type)
            ->when($requested, fn ($c) => $c->where('id', (int) $requested)->values());

        if ($candidates->isEmpty()) {
            throw new BookingException('resource_id', __('The selected :resource is not available.', ['resource' => mb_strtolower($type->resourceLabel())]));
        }

        $ids = $candidates->pluck('id')->all();
        $bookings = $this->availability->bookings($ids, $start, $end, $ignore);
        $closures = $this->availability->closures($type, $ids, $start, $end);

        $fitsGuests = fn (Resource $r) => ! $r->max_guests || $quantity <= $r->max_guests;
        $free = $candidates->first(fn (Resource $r) => $fitsGuests($r)
            && ! $this->availability->isClosed($closures, $r->id, $start, $end)
            && $this->availability->hasRoom($bookings, $r, $start, $end));

        if (! $free) {
            if ($requested && ! $fitsGuests($candidates->first())) {
                throw new BookingException('quantity', __('Maximum number of guests: :count.', ['count' => $candidates->first()->max_guests]));
            }

            throw new BookingException('start_date', __('Not available for the selected dates.'));
        }

        return new BookingDraft(
            startsAt: $start,
            endsAt: $end,
            resourceId: $free->id,
            quantity: $quantity,
            totalPrice: $free->price !== null ? round((float) $free->price * $units, 2) : null,
        );
    }

    /**
     * Interval, ki ga zaseda ena enota z začetkom na dan $day: noč (od prihoda do odhoda
     * naslednji dan) ali dan (od prevzema do vračila istega dne).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function unitInterval(ResourceType $type, Carbon $day): array
    {
        $a = Carbon::parse($day->toDateString().' '.self::checkIn($type));
        $b = Carbon::parse(($type->countsDays() ? $day : $day->copy()->addDay())->toDateString().' '.self::checkOut($type));

        // Pri dnevnem najmu z vračilom pred prevzemom (npr. 8:00 → 8:00) velja do naslednjega dne
        if ($b->lte($a)) {
            $b->addDay();
        }

        return [$a, $b];
    }

    private function resourcePayload(Resource $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'description' => $r->description,
            'image_url' => $r->image_url,
            'price' => Availability::price($r->price),
            'max_guests' => $r->max_guests,
            'properties' => $r->properties ?? [],
        ];
    }
}
