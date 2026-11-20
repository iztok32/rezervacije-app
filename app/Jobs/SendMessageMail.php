<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\MessageRecipient;
use App\Notifications\CustomerMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pošlje e-pošto vsem prejemnikom sporočila, ki je še niso dobili. Napaka pri enem prejemniku
 * se zabeleži in ne ustavi ostalih; ponoven zagon nadaljuje pri neposlanih.
 */
class SendMessageMail implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public Message $message) {}

    public function handle(): void
    {
        $this->message->recipients()
            ->where('mail_status', MessageRecipient::PENDING)
            // Po id (ne po odmiku), ker se mail_status med pošiljanjem spreminja
            ->lazyById(200)
            ->each(function (MessageRecipient $recipient) {
                try {
                    Notification::route('mail', $recipient->email)->notify(
                        (new CustomerMessage($this->message, $recipient->name))->locale($recipient->locale ?? config('app.locale'))
                    );
                    $recipient->update(['mail_status' => MessageRecipient::SENT, 'mailed_at' => now(), 'error' => null]);
                } catch (Throwable $e) {
                    report($e);
                    $recipient->update(['mail_status' => MessageRecipient::FAILED, 'error' => Str::limit($e->getMessage(), 490)]);
                }
            });

        $this->message->update(['completed_at' => now()]);
    }
}
