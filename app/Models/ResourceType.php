<?php

namespace App\Models;

use App\Enums\BookingMode;
use App\Enums\ResourceSelection;
use App\Models\Concerns\RestrictedToCustomerGroups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tip vira — določa način rezervacije in pravila za vse vire tega tipa.
 */
class ResourceType extends Model
{
    use HasFactory, RestrictedToCustomerGroups, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'color',
        'booking_mode', 'resource_label', 'resource_selection',
        'allow_guests', 'requires_approval',
        'min_advance_minutes', 'max_advance_days', 'cancellation_hours', 'reminder_hours',
        'slot_interval_minutes', 'min_duration_minutes', 'max_duration_minutes',
        'nightly_unit', 'check_in_time', 'check_out_time', 'min_units', 'max_units',
        'max_quantity_per_booking',
        'is_active', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'booking_mode' => BookingMode::class,
            'resource_selection' => ResourceSelection::class,
            'allow_guests' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'min_advance_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'cancellation_hours' => 'integer',
            'slot_interval_minutes' => 'integer',
            'min_duration_minutes' => 'integer',
            'max_duration_minutes' => 'integer',
            'min_units' => 'integer',
            'max_units' => 'integer',
            'max_quantity_per_booking' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class)->orderBy('display_order')->orderBy('name');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('display_order')->orderBy('name');
    }

    public function timeslots(): HasMany
    {
        return $this->hasMany(Timeslot::class)->orderBy('display_order')->orderBy('start_time');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class)->orderBy('weekday')->orderBy('start_time');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(Closure::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /** Ime vira za prikaz, npr. "Konj"; privzeto splošno. */
    public function resourceLabel(): string
    {
        return $this->resource_label ?: __('Resource');
    }

    /** Pri nočitvah: obračun po dnevih (najem avta) namesto po nočeh (nastanitev). */
    public function countsDays(): bool
    {
        return $this->nightly_unit === 'day';
    }
}
