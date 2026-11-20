<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interval delovnega časa za dan v tednu. resource_id = null → privzeto za cel tip.
 */
class WorkingHour extends Model
{
    protected $fillable = ['resource_type_id', 'resource_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
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
}
