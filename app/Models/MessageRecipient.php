<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prejemnik sporočila: stanje e-pošte in branje v nabiralniku portala. */
class MessageRecipient extends Model
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = [
        'message_id', 'user_id', 'name', 'email', 'locale',
        'mail_status', 'error', 'mailed_at', 'in_portal', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'mailed_at' => 'datetime',
            'in_portal' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
