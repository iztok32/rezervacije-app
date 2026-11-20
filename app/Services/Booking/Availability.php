<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Closure;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\WorkingHour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Skupni gradniki za izračun razpoložljivosti: delovni čas, zaprtja, zasedenost virov
 * in okno za rezervacije vnaprej. Vsi časi so v lokalnem času podjetja (app.timezone).
 */
class Availability
{
    /** Najdaljše obdobje, za katero izbirnik zahteva razpoložljivost naenkrat. */
    public const MAX_RANGE_DAYS = 62;

    /**
     * Ali je začetek znotraj okna za rezervacije (najmanj X minut, največ Y dni vnaprej).
     * Vrne sporočilo napake ali null.
     */
    public function advanceWindowError(ResourceType $type, Carbon $start): ?string
    {
        $now = now();

        if ($start->lt($now->copy()->addMinutes($type->min_advance_minutes))) {
            return $type->min_advance_minutes > 0
                ? __('Bookings must be made at least :hours hours in advance.', ['hours' => round($type->min_advance_minutes / 60, 1)])
                : __('This time is already in the past.');
        }

        if ($start->copy()->startOfDay()->gt($now->copy()->startOfDay()->addDays($type->max_advance_days))) {
            return __('Bookings can be made at most :days days in advance.', ['days' => $type->max_advance_days]);
        }

        return null;
    }

    public function withinAdvanceWindow(ResourceType $type, Carbon $start): bool
    {
        return $this->advanceWindowError($type, $start) === null;
    }

    /** Zadnji dan, ki ga stranka lahko izbere v izbirniku. */
    public function lastBookableDate(ResourceType $type): Carbon
    {
        return today()->addDays($type->max_advance_days);
    }

    /**
     * Aktivni viri tipa; če je podan seznam dovoljenih (npr. izvajalci storitve ali konji
     * termina) in ni prazen, samo ti.
     *
     * @param  int[]|null  $allowedIds
     * @return Collection<int, resource>
     */
    public function eligibleResources(ResourceType $type, ?array $allowedIds = null): Collection
    {
        return Resource::query()
            ->where('resource_type_id', $type->id)
            ->active()
            ->when($allowedIds, fn ($q) => $q->whereIn('id', $allowedIds))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Delovni čas vira za dan: lastni zapisi vira, sicer privzeti zapisi tipa.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    public function windows(ResourceType $type, ?Resource $resource, Carbon $date, ?Collection $hours = null): array
    {
        $hours ??= WorkingHour::where('resource_type_id', $type->id)->get();

        $own = $resource ? $hours->where('resource_id', $resource->id) : collect();
        $applicable = $own->isNotEmpty() ? $own : $hours->whereNull('resource_id');

        return $applicable
            ->where('weekday', $date->dayOfWeekIso)
            ->sortBy('start_time')
            ->map(fn (WorkingHour $h) => [
                Carbon::parse($date->toDateString().' '.$h->start_time),
                Carbon::parse($date->toDateString().' '.$h->end_time),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  int[]  $resourceIds
     * @return Collection<int, Closure>
     */
    public function closures(ResourceType $type, array $resourceIds, Carbon $from, Carbon $to): Collection
    {
        return Closure::affecting($type->id, $resourceIds, $from, $to)->get();
    }

    /**
     * Aktivne rezervacije virov, ki se prekrivajo z intervalom.
     *
     * @param  int[]  $resourceIds
     * @return Collection<int, Booking>
     */
    public function bookings(array $resourceIds, Carbon $from, Carbon $to, ?Booking $ignore = null): Collection
    {
        if (! $resourceIds) {
            return collect();
        }

        return Booking::query()
            ->active()
            ->whereIn('resource_id', $resourceIds)
            ->overlapping($from, $to)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->get(['id', 'resource_id', 'timeslot_id', 'starts_at', 'ends_at', 'blocks_until', 'quantity']);
    }

    /** Ali zaprtje (za vir ali cel tip/podjetje) prekriva interval. */
    public function isClosed(Collection $closures, ?int $resourceId, Carbon $from, Carbon $to): bool
    {
        return $closures->contains(fn (Closure $c) => $c->appliesTo($resourceId)
            && $c->starts_at->lt($to) && $c->ends_at->gt($from));
    }

    /**
     * Ali ima vir v intervalu še prostor glede na kapaciteto: največje število hkratnih
     * rezervacij kadarkoli v intervalu mora biti manjše od kapacitete vira.
     */
    public function hasRoom(Collection $bookings, Resource $resource, Carbon $from, Carbon $to): bool
    {
        $overlapping = $bookings
            ->where('resource_id', $resource->id)
            ->filter(fn (Booking $b) => $b->starts_at->lt($to) && $b->blocks_until->gt($from));

        return $this->maxConcurrent($overlapping, $from) < max(1, $resource->capacity);
    }

    /** Največje število hkrati trajajočih rezervacij (sweep po začetkih in koncih). */
    private function maxConcurrent(Collection $bookings, Carbon $from): int
    {
        if ($bookings->count() <= 1) {
            return $bookings->count();
        }

        $events = [];
        foreach ($bookings as $b) {
            $events[] = [max($b->starts_at->getTimestamp(), $from->getTimestamp()), 1];
            $events[] = [$b->blocks_until->getTimestamp(), -1];
        }
        // Konec pred začetkom ob istem času: zaporedni rezervaciji se ne prekrivata
        usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $current = $max = 0;
        foreach ($events as [, $delta]) {
            $current += $delta;
            $max = max($max, $current);
        }

        return $max;
    }

    /** Ali je interval v celoti znotraj enega od intervalov delovnega časa. */
    public function insideWindows(array $windows, Carbon $from, Carbon $to): bool
    {
        foreach ($windows as [$ws, $we]) {
            if ($from->gte($ws) && $to->lte($we)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seznam dni med from in to (vključno), omejen na MAX_RANGE_DAYS.
     *
     * @return Carbon[]
     */
    public function days(Carbon $from, Carbon $to): array
    {
        $days = [];
        $day = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();
        if ($last->diffInDays($day, true) > self::MAX_RANGE_DAYS) {
            $last = $day->copy()->addDays(self::MAX_RANGE_DAYS);
        }
        while ($day->lte($last)) {
            $days[] = $day->copy();
            $day->addDay();
        }

        return $days;
    }

    public static function price(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
