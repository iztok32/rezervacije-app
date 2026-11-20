<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Ponavljajoč se termin s kapaciteto (način "timeslot").
 */
class Timeslot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resource_type_id', 'name', 'description', 'weekdays', 'start_time', 'end_time',
        'valid_from', 'valid_to', 'capacity', 'price', 'is_active', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'valid_from' => 'date:Y-m-d',
            'valid_to' => 'date:Y-m-d',
            'capacity' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class, 'resource_type_id');
    }

    /** Viri na voljo v terminu; prazno = vsi viri tipa. */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class);
    }

    /** Zaposleni, ki vodijo termin. */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Ali termin poteka na ta dan (aktiven, pravi dan v tednu, znotraj veljavnosti). */
    public function occursOn(Carbon|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return $this->is_active
            && in_array($date->dayOfWeekIso, array_map('intval', $this->weekdays ?? []), true)
            && (! $this->valid_from || $this->valid_from->lte($date))
            && (! $this->valid_to || $this->valid_to->gte($date));
    }

    public function startsAtOn(Carbon|string $date): Carbon
    {
        return Carbon::parse(Carbon::parse($date)->toDateString().' '.$this->start_time);
    }

    public function endsAtOn(Carbon|string $date): Carbon
    {
        return Carbon::parse(Carbon::parse($date)->toDateString().' '.$this->end_time);
    }
}
