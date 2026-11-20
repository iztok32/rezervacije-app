<?php

namespace App\Services\Notifications;

use App\Jobs\SendMessageMail;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Ustvari ročno sporočilo s prejemniki in sproži pošiljanje e-pošte v čakalni vrsti. */
class MessageService
{
    public function __construct(private Audience $audience) {}

    /**
     * @param  string[]  $channels
     */
    public function send(User $sender, array $audience, array $channels, string $subject, string $body): Message
    {
        $recipients = $this->audience->resolve($audience, $sender);
        $mail = in_array(Message::CHANNEL_MAIL, $channels, true);
        $portal = in_array(Message::CHANNEL_PORTAL, $channels, true);

        $message = DB::transaction(function () use ($sender, $audience, $channels, $subject, $body, $recipients, $mail, $portal) {
            $message = Message::create([
                'sender_id' => $sender->id,
                'subject' => $subject,
                'body' => $body,
                'channels' => array_values($channels),
                'audience' => $this->audience->describe($audience),
            ]);

            $now = now();
            $rows = $recipients
                // Gost brez računa nima nabiralnika — dobi lahko samo e-pošto
                ->filter(fn ($r) => $mail || ($portal && $r['user_id']))
                ->map(fn ($r) => [
                    'message_id' => $message->id,
                    'user_id' => $r['user_id'],
                    'name' => $r['name'],
                    'email' => $r['email'],
                    'locale' => $r['locale'],
                    'mail_status' => $mail ? MessageRecipient::PENDING : null,
                    'in_portal' => $portal && $r['user_id'] !== null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($rows->chunk(500) as $chunk) {
                MessageRecipient::insert($chunk->values()->all());
            }

            $message->update([
                'recipients_count' => $rows->count(),
                'completed_at' => $mail ? null : $now,
            ]);

            return $message;
        });

        if ($mail) {
            SendMessageMail::dispatch($message);
        }

        return $message;
    }
}
