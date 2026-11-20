<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Vir, ki ga je mogoče rezervirati: konj, frizer, avto, hiška, soba, igrišče …
 */
class Resource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resource_type_id', 'name', 'description', 'image_path', 'color',
        'capacity', 'max_guests', 'price', 'user_id', 'properties',
        'is_active', 'display_order',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'max_guests' => 'integer',
            'price' => 'decimal:2',
            'properties' => 'array',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class, 'resource_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function timeslots(): BelongsToMany
    {
        return $this->belongsToMany(Timeslot::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class)->orderBy('weekday')->orderBy('start_time');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
