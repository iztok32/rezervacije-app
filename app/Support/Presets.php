<?php

namespace App\Support;

use App\Models\ResourceType;
use App\Models\Service;
use App\Models\Timeslot;
use App\Models\WorkingHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Predloge tipov virov za hitro prilagoditev aplikacije posamezni stranki. Predloga nastavi
 * način rezervacije in smiselna pravila; z $withSamples doda še vzorčne vire, storitve,
 * termine in delovni čas.
 */
class Presets
{
    public static function all(): array
    {
        return [
            'riding' => [
                'label' => __('Horse riding (sessions with horses)'),
                'type' => [
                    'name' => __('Horse riding'),
                    'description' => __('Riding lessons in groups. Choose a session and a horse.'),
                    'icon' => 'paw-print',
                    'color' => '#a16207',
                    'booking_mode' => 'timeslot',
                    'resource_label' => __('Horse'),
                    'resource_selection' => 'customer',
                    'allow_guests' => false,
                    'requires_approval' => false,
                    'cancellation_hours' => 48,
                    'max_advance_days' => 14,
                    'max_quantity_per_booking' => 1,
                ],
                'resources' => [
                    ['name' => 'Bella', 'properties' => [['label' => __('Age'), 'value' => '12']]],
                    ['name' => 'Luna', 'properties' => [['label' => __('Age'), 'value' => '9']]],
                    ['name' => 'Max', 'properties' => [['label' => __('Age'), 'value' => '15']]],
                    ['name' => 'Sultan', 'properties' => [['label' => __('Age'), 'value' => '7']]],
                ],
                'timeslots' => [
                    ['name' => __('Beginners'), 'weekdays' => [2, 4], 'start_time' => '17:00', 'end_time' => '18:00', 'capacity' => 4, 'price' => 25],
                    ['name' => __('Advanced'), 'weekdays' => [2, 4], 'start_time' => '18:00', 'end_time' => '19:00', 'capacity' => 4, 'price' => 25],
                    ['name' => __('Weekend ride'), 'weekdays' => [6], 'start_time' => '10:00', 'end_time' => '12:00', 'capacity' => 6, 'price' => 45],
                ],
            ],

            'group_classes' => [
                'label' => __('Group classes (yoga, fitness …)'),
                'type' => [
                    'name' => __('Group classes'),
                    'description' => __('Sign up for a class. Places are limited.'),
                    'icon' => 'users',
                    'color' => '#7c3aed',
                    'booking_mode' => 'timeslot',
                    'resource_label' => __('Hall'),
                    'resource_selection' => 'staff',
                    'allow_guests' => true,
                    'cancellation_hours' => 6,
                    'max_advance_days' => 21,
                    'max_quantity_per_booking' => 2,
                ],
                'resources' => [],
                'timeslots' => [
                    ['name' => __('Morning yoga'), 'weekdays' => [1, 3, 5], 'start_time' => '07:00', 'end_time' => '08:00', 'capacity' => 12, 'price' => 10],
                    ['name' => __('Pilates'), 'weekdays' => [2, 4], 'start_time' => '19:00', 'end_time' => '20:00', 'capacity' => 10, 'price' => 12],
                ],
            ],

            'hairdresser' => [
                'label' => __('Hairdresser / beauty salon (services)'),
                'type' => [
                    'name' => __('Hair salon'),
                    'description' => __('Choose a service, a hairdresser and a free time.'),
                    'icon' => 'scissors',
                    'color' => '#db2777',
                    'booking_mode' => 'service',
                    'resource_label' => __('Hairdresser'),
                    'resource_selection' => 'auto',
                    'allow_guests' => true,
                    'cancellation_hours' => 24,
                    'min_advance_minutes' => 60,
                    'max_advance_days' => 60,
                    'slot_interval_minutes' => 15,
                ],
                'resources' => [
                    ['name' => 'Maja'],
                    ['name' => 'Tina'],
                ],
                'services' => [
                    ['name' => __('Men\'s haircut'), 'duration_minutes' => 30, 'buffer_minutes' => 0, 'price' => 18],
                    ['name' => __('Women\'s haircut'), 'duration_minutes' => 45, 'buffer_minutes' => 15, 'price' => 30],
                    ['name' => __('Colouring'), 'duration_minutes' => 90, 'buffer_minutes' => 15, 'price' => 55],
                ],
                'hours' => [
                    [1, '08:00', '16:00'], [2, '12:00', '20:00'], [3, '08:00', '16:00'],
                    [4, '12:00', '20:00'], [5, '08:00', '14:00'], [6, '08:00', '12:00'],
                ],
            ],

            'car_rental' => [
                'label' => __('Car rental (days)'),
                'type' => [
                    'name' => __('Car rental'),
                    'description' => __('Choose a car and the rental period.'),
                    'icon' => 'car',
                    'color' => '#2563eb',
                    'booking_mode' => 'nightly',
                    'resource_label' => __('Car'),
                    'resource_selection' => 'customer',
                    'allow_guests' => true,
                    'requires_approval' => true,
                    'cancellation_hours' => 48,
                    'max_advance_days' => 365,
                    'nightly_unit' => 'day',
                    'check_in_time' => '08:00',
                    'check_out_time' => '18:00',
                    'min_units' => 1,
                    'max_units' => 30,
                ],
                'resources' => [
                    ['name' => 'VW Golf', 'max_guests' => 5, 'price' => 45, 'properties' => [['label' => __('Transmission'), 'value' => __('Manual')], ['label' => __('Fuel'), 'value' => __('Diesel')]]],
                    ['name' => 'Škoda Octavia Combi', 'max_guests' => 5, 'price' => 55, 'properties' => [['label' => __('Transmission'), 'value' => __('Automatic')], ['label' => __('Fuel'), 'value' => __('Petrol')]]],
                    ['name' => 'Renault Trafic', 'max_guests' => 9, 'price' => 85, 'properties' => [['label' => __('Seats'), 'value' => '9']]],
                ],
            ],

            'glamping' => [
                'label' => __('Glamping / holiday houses (nights)'),
                'type' => [
                    'name' => __('Glamping houses'),
                    'description' => __('Choose a house and the dates of your stay.'),
                    'icon' => 'tent',
                    'color' => '#15803d',
                    'booking_mode' => 'nightly',
                    'resource_label' => __('House'),
                    'resource_selection' => 'customer',
                    'allow_guests' => true,
                    'requires_approval' => true,
                    'cancellation_hours' => 168,
                    'max_advance_days' => 365,
                    'nightly_unit' => 'night',
                    'check_in_time' => '15:00',
                    'check_out_time' => '10:00',
                    'min_units' => 2,
                    'max_units' => 14,
                ],
                'resources' => [
                    ['name' => __('Forest house'), 'max_guests' => 2, 'price' => 120, 'properties' => [['label' => __('Beds'), 'value' => '1 × 180 cm'], ['label' => __('Hot tub'), 'value' => __('Yes')]]],
                    ['name' => __('Meadow house'), 'max_guests' => 4, 'price' => 150, 'properties' => [['label' => __('Beds'), 'value' => '2 × 160 cm']]],
                ],
            ],

            'hotel' => [
                'label' => __('Hotel / apartments (nights)'),
                'type' => [
                    'name' => __('Rooms'),
                    'description' => __('Choose the dates of your stay and a room.'),
                    'icon' => 'bed-double',
                    'color' => '#0f766e',
                    'booking_mode' => 'nightly',
                    'resource_label' => __('Room'),
                    'resource_selection' => 'customer',
                    'allow_guests' => true,
                    'cancellation_hours' => 72,
                    'max_advance_days' => 365,
                    'nightly_unit' => 'night',
                    'check_in_time' => '14:00',
                    'check_out_time' => '11:00',
                    'min_units' => 1,
                ],
                'resources' => [
                    ['name' => __('Double room 101'), 'max_guests' => 2, 'price' => 89],
                    ['name' => __('Double room 102'), 'max_guests' => 2, 'price' => 89],
                    ['name' => __('Family room 201'), 'max_guests' => 4, 'price' => 139],
                ],
            ],

            'court' => [
                'label' => __('Court / hall rental (hours)'),
                'type' => [
                    'name' => __('Tennis courts'),
                    'description' => __('Book a court by the hour.'),
                    'icon' => 'trophy',
                    'color' => '#ea580c',
                    'booking_mode' => 'hourly',
                    'resource_label' => __('Court'),
                    'resource_selection' => 'customer',
                    'allow_guests' => false,
                    'cancellation_hours' => 12,
                    'max_advance_days' => 14,
                    'slot_interval_minutes' => 60,
                    'min_duration_minutes' => 60,
                    'max_duration_minutes' => 180,
                ],
                'resources' => [
                    ['name' => __('Court 1'), 'price' => 15],
                    ['name' => __('Court 2'), 'price' => 15],
                ],
                'hours' => [
                    [1, '07:00', '22:00'], [2, '07:00', '22:00'], [3, '07:00', '22:00'],
                    [4, '07:00', '22:00'], [5, '07:00', '22:00'], [6, '08:00', '20:00'], [7, '08:00', '20:00'],
                ],
            ],
        ];
    }

    public static function options(): array
    {
        return collect(self::all())
            ->map(fn ($p, $key) => ['value' => $key, 'label' => $p['label'], 'mode' => $p['type']['booking_mode']])
            ->values()
            ->all();
    }

    /** Ustvari tip vira po predlogi (in po želji vzorčne vire, storitve, termine, delovni čas). */
    public static function apply(string $key, bool $withSamples = true, array $overrides = []): ResourceType
    {
        $preset = self::all()[$key] ?? throw new \InvalidArgumentException("Unknown preset [$key]");

        return DB::transaction(function () use ($preset, $withSamples, $overrides) {
            $attributes = array_merge($preset['type'], $overrides);
            $attributes['slug'] = self::uniqueSlug($attributes['slug'] ?? $attributes['name']);
            $attributes['display_order'] = (int) ResourceType::max('display_order') + 1;

            $type = ResourceType::create($attributes);

            if (! $withSamples) {
                return $type;
            }

            foreach ($preset['resources'] ?? [] as $i => $r) {
                $type->resources()->create($r + ['display_order' => $i]);
            }
            foreach ($preset['services'] ?? [] as $i => $s) {
                Service::create($s + ['resource_type_id' => $type->id, 'display_order' => $i]);
            }
            foreach ($preset['timeslots'] ?? [] as $i => $t) {
                Timeslot::create($t + ['resource_type_id' => $type->id, 'display_order' => $i]);
            }
            foreach ($preset['hours'] ?? [] as [$day, $from, $to]) {
                WorkingHour::create(['resource_type_id' => $type->id, 'weekday' => $day, 'start_time' => $from, 'end_time' => $to]);
            }

            return $type;
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tip';
        $slug = $base;
        $i = 2;
        while (ResourceType::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
