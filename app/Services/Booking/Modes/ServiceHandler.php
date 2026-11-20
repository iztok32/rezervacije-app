<?php

namespace App\Services\Booking\Modes;

use App\Enums\ResourceSelection;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingDraft;
use App\Services\Booking\BookingException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Storitve s trajanjem: stranka izbere storitev, izvajalca (ali "kdorkoli") in prost začetek.
 * Prosti začetki se izračunajo iz delovnega časa, zaprtij in obstoječih rezervacij.
 */
class ServiceHandler implements BookingModeHandler
{
    public function __construct(private Availability $availability) {}

    public function rules(ResourceType $type): array
    {
        return [
            'service_id' => 'required|integer',
            'resource_id' => 'nullable|integer',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
        ];
    }

    public function availabilityRules(): array
    {
        return [
            'service_id' => 'required|integer',
            'resource_id' => 'nullable|integer',
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
        ];
    }

    public function availability(ResourceType $type, array $query, ?User $viewer): array
    {
        $service = $this->service($type, $query['service_id']);
        $resources = $this->resourcesFor($type, $service, $query['resource_id'] ?? null);
        $days = $this->availability->days(Carbon::parse($query['from']), Carbon::parse($query['to']));

        if (! $days || $resources->isEmpty()) {
            return ['days' => []];
        }

        $from = $days[0]->copy();
        $to = end($days)->copy()->addDay();
        $hours = WorkingHour::where('resource_type_id', $type->id)->get();
        $ids = $resources->pluck('id')->all();

        $bookings = $this->availability->bookings($ids, $from, $to);
        $closures = $this->availability->closures($type, $ids, $from, $to);

        $result = [];
        foreach ($days as $day) {
            $byTime = [];
            foreach ($resources as $resource) {
                foreach ($this->freeStarts($type, $service, $resource, $day, $hours, $bookings, $closures, false) as $time) {
                    $byTime[$time][] = $resource->id;
                }
            }
            ksort($byTime);

            $result[] = [
                'date' => $day->toDateString(),
                'slots' => array_map(fn ($time, $ids) => ['time' => $time, 'resource_ids' => $ids], array_keys($byTime), $byTime),
            ];
        }

        return ['days' => $result];
    }

    public function draft(ResourceType $type, array $input, bool $privileged, ?Booking $ignore = null): BookingDraft
    {
        $service = $this->service($type, $input['service_id']);
        $start = Carbon::parse($input['date'].' '.$input['time']);
        $end = $start->copy()->addMinutes($service->duration_minutes);
        $blocks = $end->copy()->addMinutes($service->buffer_minutes);

        if (! $privileged && ($error = $this->availability->advanceWindowError($type, $start))) {
            throw new BookingException('time', $error);
        }

        $requested = $input['resource_id'] ?? null;
        if (! $requested && $type->resource_selection === ResourceSelection::Customer) {
            throw new BookingException('resource_id', __('Please choose: :resource.', ['resource' => mb_strtolower($type->resourceLabel())]));
        }

        $candidates = $this->resourcesFor($type, $service, $requested);
        if ($candidates->isEmpty()) {
            throw new BookingException('resource_id', __('The selected :resource does not offer this service.', ['resource' => mb_strtolower($type->resourceLabel())]));
        }

        $ids = $candidates->pluck('id')->all();
        $hours = WorkingHour::where('resource_type_id', $type->id)->get();
        $bookings = $this->availability->bookings($ids, $start, $blocks, $ignore);
        $closures = $this->availability->closures($type, $ids, $start, $end);

        $free = $candidates->first(fn (Resource $r) => $this->isFree($type, $r, $start, $end, $blocks, $hours, $bookings, $closures));

        if (! $free) {
            throw new BookingException('time', __('The selected time is no longer available. Please choose another time.'));
        }

        return new BookingDraft(
            startsAt: $start,
            endsAt: $end,
            blocksUntil: $blocks,
            resourceId: $free->id,
            serviceId: $service->id,
            totalPrice: Availability::price($service->price),
        );
    }

    private function service(ResourceType $type, int $serviceId): Service
    {
        $service = Service::with('resources:id')
            ->where('resource_type_id', $type->id)
            ->active()
            ->find($serviceId);

        if (! $service) {
            throw new BookingException('service_id', __('The selected service is not available.'));
        }

        return $service;
    }

    /** Izvajalci storitve (vsi viri tipa, če storitev nima omejitve), po potrebi samo izbrani. */
    private function resourcesFor(ResourceType $type, Service $service, ?int $requested): Collection
    {
        $allowed = $service->resources->pluck('id')->all();
        $resources = $this->availability->eligibleResources($type, $allowed ?: null);

        return $requested ? $resources->where('id', $requested)->values() : $resources;
    }

    private function isFree(ResourceType $type, Resource $resource, Carbon $start, Carbon $end, Carbon $blocks, Collection $hours, Collection $bookings, Collection $closures): bool
    {
        $windows = $this->availability->windows($type, $resource, $start->copy()->startOfDay(), $hours);

        return $this->availability->insideWindows($windows, $start, $end)
            && ! $this->availability->isClosed($closures, $resource->id, $start, $end)
            && $this->availability->hasRoom($bookings, $resource, $start, $blocks);
    }

    /**
     * Prosti začetki (H:i) za vir na dan: koraki slot_interval od začetka vsakega intervala
     * delovnega časa, storitev se mora končati pred koncem intervala.
     *
     * @return string[]
     */
    private function freeStarts(ResourceType $type, Service $service, Resource $resource, Carbon $day, Collection $hours, Collection $bookings, Collection $closures, bool $privileged): array
    {
        $step = max(5, $type->slot_interval_minutes);
        $starts = [];

        foreach ($this->availability->windows($type, $resource, $day, $hours) as [$ws, $we]) {
            for ($t = $ws->copy(); $t->copy()->addMinutes($service->duration_minutes)->lte($we); $t->addMinutes($step)) {
                $end = $t->copy()->addMinutes($service->duration_minutes);
                $blocks = $end->copy()->addMinutes($service->buffer_minutes);

                if (! $privileged && ! $this->availability->withinAdvanceWindow($type, $t)) {
                    continue;
                }
                if ($this->availability->isClosed($closures, $resource->id, $t, $end)) {
                    continue;
                }
                if (! $this->availability->hasRoom($bookings, $resource, $t, $blocks)) {
                    continue;
                }

                $starts[] = $t->format('H:i');
            }
        }

        return $starts;
    }
}
