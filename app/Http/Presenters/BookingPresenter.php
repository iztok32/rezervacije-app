<?php

namespace App\Http\Presenters;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Services\Booking\Modes\NightlyHandler;

/**
 * Pretvorba modelov v podatke za Inertia strani (samo polja, ki jih vmesnik potrebuje).
 */
class BookingPresenter
{
    public static function type(ResourceType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'slug' => $type->slug,
            'description' => $type->description,
            'icon' => $type->icon,
            'color' => $type->color,
            'booking_mode' => $type->booking_mode->value,
            'resource_label' => $type->resourceLabel(),
            'resource_selection' => $type->resource_selection->value,
            'allow_guests' => $type->allow_guests,
            'requires_approval' => $type->requires_approval,
            'max_quantity_per_booking' => max(1, $type->max_quantity_per_booking),
            'cancellation_hours' => $type->cancellation_hours,
            'reminder_hours' => $type->reminder_hours,
            'min_advance_minutes' => $type->min_advance_minutes,
            'max_advance_days' => $type->max_advance_days,
            'slot_interval_minutes' => $type->slot_interval_minutes,
            'min_duration_minutes' => $type->min_duration_minutes,
            'max_duration_minutes' => $type->max_duration_minutes,
            'nightly_unit' => $type->countsDays() ? 'day' : 'night',
            'check_in_time' => NightlyHandler::checkIn($type),
            'check_out_time' => NightlyHandler::checkOut($type),
            'min_units' => max(1, $type->min_units),
            'max_units' => $type->max_units,
            'is_active' => $type->is_active,
        ];
    }

    public static function resource(Resource $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'description' => $r->description,
            'image_url' => $r->image_url,
            'color' => $r->color,
            'price' => $r->price !== null ? (float) $r->price : null,
            'capacity' => $r->capacity,
            'max_guests' => $r->max_guests,
            'properties' => $r->properties ?? [],
        ];
    }

    public static function booking(Booking $b, bool $forStaff = false): array
    {
        $b->loadMissing(['type', 'resource', 'service', 'timeslot']);

        $data = [
            'id' => $b->id,
            'reference' => $b->reference,
            'title' => $b->title(),
            'status' => $b->status->value,
            'status_label' => $b->status->label(),
            'starts_at' => $b->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $b->ends_at->format('Y-m-d\TH:i'),
            'quantity' => $b->quantity,
            'total_price' => $b->total_price !== null ? (float) $b->total_price : null,
            'notes' => $b->notes,
            'customer_name' => $b->customer_name,
            'customer_email' => $b->customer_email,
            'customer_phone' => $b->customer_phone,
            'created_at' => $b->created_at?->format('Y-m-d\TH:i'),
            'cancelled_at' => $b->cancelled_at?->format('Y-m-d\TH:i'),
            'cancellation_reason' => $b->cancellation_reason,
            'type' => $b->type ? [
                'id' => $b->type->id,
                'name' => $b->type->name,
                'slug' => $b->type->slug,
                'icon' => $b->type->icon,
                'color' => $b->type->color,
                'booking_mode' => $b->type->booking_mode->value,
                'resource_label' => $b->type->resourceLabel(),
                'nightly_unit' => $b->type->countsDays() ? 'day' : 'night',
            ] : null,
            'resource' => $b->resource ? ['id' => $b->resource->id, 'name' => $b->resource->name, 'color' => $b->resource->color] : null,
            'service' => $b->service ? ['id' => $b->service->id, 'name' => $b->service->name] : null,
            'timeslot' => $b->timeslot ? ['id' => $b->timeslot->id, 'name' => $b->timeslot->name] : null,
        ];

        if ($forStaff) {
            $b->loadMissing(['user:id,name,email', 'createdBy:id,name']);
            $data += [
                'internal_notes' => $b->internal_notes,
                'user' => $b->user ? ['id' => $b->user->id, 'name' => $b->user->name, 'email' => $b->user->email] : null,
                'created_by' => $b->createdBy?->name,
                'confirmed_at' => $b->confirmed_at?->format('Y-m-d\TH:i'),
            ];
        }

        return $data;
    }
}
