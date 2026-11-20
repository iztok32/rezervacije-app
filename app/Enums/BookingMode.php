<?php

namespace App\Enums;

/**
 * Način rezervacije, ki ga določi tip vira.
 */
enum BookingMode: string
{
    /** Ponavljajoč se termin s kapaciteto (jahanje, skupinska vadba). */
    case Timeslot = 'timeslot';

    /** Storitev s trajanjem pri izvajalcu (frizer, masaža). */
    case Service = 'service';

    /** Obdobje od datuma do datuma (najem avta, hiška, hotelska soba). */
    case Nightly = 'nightly';

    /** Prosto izbran interval ur (igrišče, dvorana, kolo). */
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Timeslot => __('Scheduled sessions with capacity'),
            self::Service => __('Services with duration'),
            self::Nightly => __('Days / nights (from–to)'),
            self::Hourly => __('Hourly rental'),
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
