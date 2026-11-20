<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dogodek v zgodovini rezervacije (oddana, sprememba statusa, dodelitev vira, opombe, opomnik).
 * user_id = kdo je spremembo naredil; null = gost/stranka brez računa ali sistem.
 */
class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    public const CREATED = 'created';

    public const STATUS = 'status_changed';

    public const RESOURCE = 'resource_changed';

    public const NOTES = 'notes_updated';

    public const REMINDER = 'reminder_sent';

    protected $fillable = ['booking_id', 'user_id', 'event', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
