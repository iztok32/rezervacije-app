<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Skupina strank (npr. Člani, Napredni jahači). Uporablja se za omejitev dostopa do ponudbe
 * in terminov ter kasneje za obveščanje.
 */
class CustomerGroup extends Model
{
    protected $fillable = ['name', 'description', 'color', 'display_order'];

    protected function casts(): array
    {
        return ['display_order' => 'integer'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function resourceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ResourceType::class);
    }

    public function timeslots(): BelongsToMany
    {
        return $this->belongsToMany(Timeslot::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
