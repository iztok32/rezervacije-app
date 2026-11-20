<?php

namespace App\Services\Booking\Modes;

use App\Enums\ResourceSelection;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingDraft;
use App\Services\Booking\BookingException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Urni najem: stranka izbere vir (ali "kateregakoli"), dan ter začetek in konec v korakih
 * slot_interval znotraj delovnega časa.
 */
class HourlyHandler implements BookingModeHandler
{
    public function __construct(private Availability $availability) {}

    public function rules(ResourceType $type): array
    {
        return [
            'resource_id' => 'nullable|integer',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'quantity' => 'nullable|integer|min:1|max:'.max(1, $type->max_quantity_per_booking),
        ];
    }

    public function availabilityRules(): array
    {
        return [
            'date' => 'required|date_format:Y-m-d',
            'resource_id' => 'nullable|integer',
        ];
    }

    public function availability(ResourceType $type, array $query, ?User $viewer): array
    {
        $day = Carbon::parse($query['date'])->startOfDay();
        $resources = $this->availability->eligibleResources($type);
        if (! empty($query['resource_id'])) {
            $resources = $resources->where('id', (int) $query['resource_id'])->values();
        }

        $ids = $resources->pluck('id')->all();
        $next = $day->copy()->addDay();
        $hours = WorkingHour::where('resource_type_id', $type->id)->get();
        $bookings = $this->availability->bookings($ids, $day, $next);
        $closures = $this->availability->closures($type, $ids, $day, $next);

        $perResource = $resources->map(fn (Resource $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'price' => Availability::price($r->price),
            'starts' => $this->starts($type, $r, $day, $hours, $bookings, $closures, false),
        ])->values();

        // "Kdorkoli": za vsak začetek najdaljši konec med vsemi viri
        $any = [];
        foreach ($perResource as $r) {
            foreach ($r['starts'] as $s) {
                if (! isset($any[$s['time']]) || $s['max_end'] > $any[$s['time']]) {
                    $any[$s['time']] = $s['max_end'];
                }
            }
        }
        ksort($any);

        return [
            'date' => $day->toDateString(),
            'step' => $this->step($type),
            'min_duration' => $this->minDuration($type),
            'max_duration' => $type->max_duration_minutes,
            'resources' => $perResource->all(),
            'any' => array_map(fn ($t, $e) => ['time' => $t, 'max_end' => $e], array_keys($any), $any),
        ];
    }

    public function draft(ResourceType $type, array $input, bool $privileged, ?Booking $ignore = null): BookingDraft
    {
        $start = Carbon::parse($input['date'].' '.$input['start_time']);
        $end = Carbon::parse($input['date'].' '.$input['end_time']);
        // Konec ob polnoči ("00:00") pomeni konec dneva
        if ($input['end_time'] === '00:00') {
            $end->addDay();
        }

        $minutes = (int) $start->diffInMinutes($end, false);
        $step = $this->step($type);

        if ($minutes <= 0) {
            throw new BookingException('end_time', __('The end time must be after the start time.'));
        }
        if ($minutes % $step !== 0) {
            throw new BookingException('end_time', __('The duration must be a multiple of :minutes minutes.', ['minutes' => $step]));
        }
        if ($minutes < $this->minDuration($type)) {
            throw new BookingException('end_time', __('The minimum duration is :minutes minutes.', ['minutes' => $this->minDuration($type)]));
        }
        if ($type->max_duration_minutes && $minutes > $type->max_duration_minutes) {
            throw new BookingException('end_time', __('The maximum duration is :minutes minutes.', ['minutes' => $type->max_duration_minutes]));
        }
        if (! $privileged && ($error = $this->availability->advanceWindowError($type, $start))) {
            throw new BookingException('start_time', $error);
        }

        $requested = $input['resource_id'] ?? null;
        if (! $requested && $type->resource_selection === ResourceSelection::Customer) {
            throw new BookingException('resource_id', __('Please choose: :resource.', ['resource' => mb_strtolower($type->resourceLabel())]));
        }

        $candidates = $this->availability->eligibleResources($type);
        if ($requested) {
            $candidates = $candidates->where('id', (int) $requested)->values();
            if ($candidates->isEmpty()) {
                throw new BookingException('resource_id', __('The selected :resource is not available.', ['resource' => mb_strtolower($type->resourceLabel())]));
            }
        }

        $ids = $candidates->pluck('id')->all();
        $hours = WorkingHour::where('resource_type_id', $type->id)->get();
        $bookings = $this->availability->bookings($ids, $start, $end, $ignore);
        $closures = $this->availability->closures($type, $ids, $start, $end);

        $free = $candidates->first(function (Resource $r) use ($type, $start, $end, $hours, $bookings, $closures) {
            $windows = $this->availability->windows($type, $r, $start->copy()->startOfDay(), $hours);

            return $this->availability->insideWindows($windows, $start, $end)
                && ! $this->availability->isClosed($closures, $r->id, $start, $end)
                && $this->availability->hasRoom($bookings, $r, $start, $end);
        });

        if (! $free) {
            throw new BookingException('start_time', __('The selected time is no longer available. Please choose another time.'));
        }

        return new BookingDraft(
            startsAt: $start,
            endsAt: $end,
            resourceId: $free->id,
            quantity: (int) ($input['quantity'] ?? 1),
            totalPrice: $free->price !== null ? round((float) $free->price * $minutes / 60, 2) : null,
        );
    }

    private function step(ResourceType $type): int
    {
        return max(5, $type->slot_interval_minutes);
    }

    private function minDuration(ResourceType $type): int
    {
        return max($this->step($type), (int) $type->min_duration_minutes);
    }

    /**
     * Možni začetki za vir na dan in za vsakega najkasnejši konec (H:i), do katerega je vir
     * neprekinjeno prost.
     *
     * @return array<int, array{time: string, max_end: string}>
     */
    private function starts(ResourceType $type, Resource $resource, Carbon $day, Collection $hours, Collection $bookings, Collection $closures, bool $privileged): array
    {
        $step = $this->step($type);
        $min = $this->minDuration($type);
        $max = $type->max_duration_minutes;
        $result = [];

        $free = fn (Carbon $a, Carbon $b) => ! $this->availability->isClosed($closures, $resource->id, $a, $b)
            && $this->availability->hasRoom($bookings, $resource, $a, $b);

        foreach ($this->availability->windows($type, $resource, $day, $hours) as [$ws, $we]) {
            for ($t = $ws->copy(); $t->copy()->addMinutes($min)->lte($we); $t->addMinutes($step)) {
                if (! $privileged && ! $this->availability->withinAdvanceWindow($type, $t)) {
                    continue;
                }

                $limit = $max ? $t->copy()->addMinutes($max)->min($we) : $we->copy();
                $maxEnd = null;
                for ($e = $t->copy()->addMinutes($step); $e->lte($limit); $e->addMinutes($step)) {
                    // Interval [e - step, e) mora biti prost, da se najem lahko podaljša do e
                    if (! $free($e->copy()->subMinutes($step), $e->copy())) {
                        break;
                    }
                    $maxEnd = $e->copy();
                }

                if ($maxEnd && $t->diffInMinutes($maxEnd) >= $min) {
                    $result[] = [
                        'time' => $t->format('H:i'),
                        'max_end' => $maxEnd->isSameDay($t) ? $maxEnd->format('H:i') : '24:00',
                    ];
                }
            }
        }

        return $result;
    }
}
