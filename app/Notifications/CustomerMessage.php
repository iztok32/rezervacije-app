<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Ročno sporočilo osebja strankam (e-poštni kanal). */
class CustomerMessage extends Notification
{
    public function __construct(public Message $message, public string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->message->subject.' · '.Setting::businessName())
            ->greeting(__('Hello :name,', ['name' => $this->name]));

        // Odstavki iz besedila (prazna vrstica = nov odstavek)
        foreach (preg_split('/\R{2,}/', trim($this->message->body)) as $paragraph) {
            $mail->line(Str::of($paragraph)->trim()->replace("\n", "  \n")->toString());
        }

        return $mail->salutation(__('Kind regards,')."\n".Setting::businessName());
    }
}
