<?php

namespace App\Services\Booking\Modes;

use App\Enums\ResourceSelection;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Timeslot;
use App\Models\User;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingDraft;
use App\Services\Booking\BookingException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Termini s kapaciteto: stranka izbere termin na določen dan (in po potrebi vir, npr. konja).
 */
class TimeslotHandler implements BookingModeHandler
{
    public function __construct(private Availability $availability) {}

    public function rules(ResourceType $type): array
    {
        return [
            'timeslot_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'resource_id' => 'nullable|integer',
            'quantity' => 'nullable|integer|min:1|max:'.max(1, $type->max_quantity_per_booking),
        ];
    }

    public function availabilityRules(): array
    {
        return [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
        ];
    }

    public function availability(ResourceType $type, array $query, ?User $viewer): array
    {
        $days = $this->availability->days(Carbon::parse($query['from']), Carbon::parse($query['to']));
        if (! $days) {
            return ['occurrences' => []];
        }

        $from = $days[0]->copy();
        $to = end($days)->copy()->endOfDay();

        $timeslots = Timeslot::with(['resources:id', 'staff:id,name'])
            ->where('resource_type_id', $type->id)
            ->active()
            // Termini, omejeni na skupine strank, so vidni samo članom (in osebju)
            ->when(! $viewer?->canBookForOthers($type), fn ($q) => $q->openTo($viewer))
            ->orderBy('start_time')
            ->orderBy('display_order')
            ->get();

        $allResources = $this->availability->eligibleResources($type);
        $resourceIds = $allResources->pluck('id')->all();

        $taken = Booking::active()
            ->whereIn('timeslot_id', $timeslots->pluck('id'))
            ->whereBetween('starts_at', [$from, $to])
            ->get(['id', 'timeslot_id', 'starts_at', 'quantity', 'user_id']);

        $resourceBookings = $this->availability->bookings($resourceIds, $from, $to);
        $closures = $this->availability->closures($type, $resourceIds, $from, $to);

        $occurrences = [];
        foreach ($days as $day) {
            foreach ($timeslots as $slot) {
                if (! $slot->occursOn($day)) {
                    continue;
                }

                $start = $slot->startsAtOn($day);
                $end = $slot->endsAtOn($day);

                $slotBookings = $taken->filter(fn (Booking $b) => $b->timeslot_id === $slot->id && $b->starts_at->eq($start));
                $booked = (int) $slotBookings->sum('quantity');
                $remaining = $slot->capacity ? max(0, $slot->capacity - $booked) : null;

                $status = match (true) {
                    $this->availability->isClosed($closures, null, $start, $end) => 'closed',
                    $start->lt(now()) => 'past',
                    ! $this->availability->withinAdvanceWindow($type, $start) => 'unavailable',
                    $remaining === 0 => 'full',
                    default => 'open',
                };

                $occurrence = [
                    'key' => $slot->id.'@'.$day->toDateString(),
                    'timeslot_id' => $slot->id,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'date' => $day->toDateString(),
                    'start' => $start->format('H:i'),
                    'end' => $end->format('H:i'),
                    'capacity' => $slot->capacity,
                    'booked' => $booked,
                    'remaining' => $remaining,
                    'price' => Availability::price($slot->price),
                    'status' => $status,
                    'staff' => $slot->staff->pluck('name')->all(),
                    'mine' => $viewer ? $slotBookings->contains('user_id', $viewer->id) : false,
                ];

                if ($type->resource_selection === ResourceSelection::Customer) {
                    $occurrence['resources'] = $this->resourcesFor($slot, $allResources)
                        ->map(fn (Resource $r) => [
                            'id' => $r->id,
                            'name' => $r->name,
                            'available' => ! $this->availability->isClosed($closures, $r->id, $start, $end)
                                && $this->availability->hasRoom($resourceBookings, $r, $start, $end),
                        ])->values()->all();
                }

                $occurrences[] = $occurrence;
            }
        }

        return ['occurrences' => $occurrences];
    }

    public function draft(ResourceType $type, array $input, bool $privileged, ?Booking $ignore = null): BookingDraft
    {
        $slot = Timeslot::with('resources:id')
            ->where('resource_type_id', $type->id)
            ->find($input['timeslot_id']);

        if (! $slot || ! $slot->occursOn($input['date'])) {
            throw new BookingException('timeslot_id', __('This session does not take place on the selected date.'));
        }

        $start = $slot->startsAtOn($input['date']);
        $end = $slot->endsAtOn($input['date']);
        $quantity = (int) ($input['quantity'] ?? 1);

        if (! $privileged && ($error = $this->availability->advanceWindowError($type, $start))) {
            throw new BookingException('date', $error);
        }

        if ($slot->capacity) {
            $booked = (int) Booking::active()
                ->where('timeslot_id', $slot->id)
                ->where('starts_at', $start)
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->sum('quantity');

            if ($booked + $quantity > $slot->capacity) {
                throw new BookingException('timeslot_id', $booked >= $slot->capacity
                    ? __('This session is fully booked.')
                    : __('Only :count places are still available.', ['count' => $slot->capacity - $booked]));
            }
        }

        $candidates = $this->resourcesFor($slot, $this->availability->eligibleResources($type));
        $closures = $this->availability->closures($type, $candidates->pluck('id')->all(), $start, $end);

        if ($this->availability->isClosed($closures, null, $start, $end)) {
            throw new BookingException('date', __('We are closed at the selected time.'));
        }

        $resourceId = $this->pickResource($type, $candidates, $closures, $input['resource_id'] ?? null, $start, $end, $ignore);

        return new BookingDraft(
            startsAt: $start,
            endsAt: $end,
            resourceId: $resourceId,
            timeslotId: $slot->id,
            quantity: $quantity,
            totalPrice: $slot->price !== null ? round((float) $slot->price * $quantity, 2) : null,
        );
    }

    /** Viri termina; brez omejitve vsi aktivni viri tipa. */
    private function resourcesFor(Timeslot $slot, Collection $all): Collection
    {
        $allowed = $slot->resources->pluck('id');

        return $allowed->isEmpty() ? $all : $all->whereIn('id', $allowed)->values();
    }

    private function pickResource(ResourceType $type, Collection $candidates, Collection $closures, ?int $requested, Carbon $start, Carbon $end, ?Booking $ignore): ?int
    {
        $label = $type->resourceLabel();

        $isFree = function (Resource $r) use ($closures, $start, $end, $ignore) {
            $bookings = $this->availability->bookings([$r->id], $start, $end, $ignore);

            return ! $this->availability->isClosed($closures, $r->id, $start, $end)
                && $this->availability->hasRoom($bookings, $r, $start, $end);
        };

        if ($requested) {
            $resource = $candidates->firstWhere('id', $requested);
            if (! $resource || ! $isFree($resource)) {
                throw new BookingException('resource_id', __('The selected :resource is not available at this time.', ['resource' => mb_strtolower($label)]));
            }

            return $resource->id;
        }

        // Vir izbere stranka: obvezna izbira, če tip vire sploh ima
        if ($type->resource_selection === ResourceSelection::Customer && $candidates->isNotEmpty()) {
            throw new BookingException('resource_id', __('Please choose: :resource.', ['resource' => mb_strtolower($label)]));
        }

        // Samodejna dodelitev prvega prostega vira (pri "staff" dodeli osebje kasneje)
        if ($type->resource_selection === ResourceSelection::Auto && $candidates->isNotEmpty()) {
            $free = $candidates->first($isFree);
            if (! $free) {
                throw new BookingException('timeslot_id', __('No :resource is available at this time.', ['resource' => mb_strtolower($label)]));
            }

            return $free->id;
        }

        return null;
    }
}
