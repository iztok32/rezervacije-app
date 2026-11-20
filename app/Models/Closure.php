<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zaprtje / odsotnost. Obseg: vir, cel tip ali celotno podjetje (oboje null).
 */
class Closure extends Model
{
    protected $fillable = ['resource_type_id', 'resource_id', 'starts_at', 'ends_at', 'reason'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class, 'resource_type_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * Zaprtja, ki se prekrivajo z intervalom [from, to) in zadevajo podjetje, tip ali
     * katerega od podanih virov.
     *
     * @param  int[]  $resourceIds
     */
    public function scopeAffecting(Builder $query, int $typeId, array $resourceIds, Carbon $from, Carbon $to): Builder
    {
        return $query
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->where(function (Builder $q) use ($typeId, $resourceIds) {
                $q->where(fn ($q) => $q->whereNull('resource_type_id')->whereNull('resource_id'))
                    ->orWhere(fn ($q) => $q->where('resource_type_id', $typeId)->whereNull('resource_id'));
                if ($resourceIds) {
                    $q->orWhereIn('resource_id', $resourceIds);
                }
            });
    }

    /** Ali zaprtje velja za vir (oz. za cel tip, ko je $resourceId null). */
    public function appliesTo(?int $resourceId): bool
    {
        return $this->resource_id === null || $this->resource_id === $resourceId;
    }
}
