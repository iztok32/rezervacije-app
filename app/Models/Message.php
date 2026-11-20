<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ročno sporočilo strankam. Prejemniki se določijo ob pošiljanju (MessageRecipient);
 * e-pošta se pošlje v čakalni vrsti, nabiralnik v portalu je takoj na voljo.
 */
class Message extends Model
{
    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_PORTAL = 'portal';

    protected $fillable = ['sender_id', 'subject', 'body', 'channels', 'audience', 'recipients_count', 'completed_at'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'recipients_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    public function usesChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? [], true);
    }
}
