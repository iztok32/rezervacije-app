<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Stranki: opomnik pred začetkom rezervacije (ukaz bookings:send-reminders). */
class BookingReminder extends Notification
{
    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking->loadMissing(['type', 'resource', 'service', 'timeslot']);

        $mail = (new MailMessage)
            ->subject(__('Reminder: :booking', ['booking' => $b->title()]).' · '.Setting::businessName())
            ->greeting(__('Hello :name,', ['name' => $b->customer_name]))
            ->line(__('We are looking forward to seeing you. Here is a reminder of your booking.'));

        BookingMailDetails::append($mail, $b);

        if ($address = Setting::get('business.address')) {
            $mail->line(__('Address').': '.$address);
        }

        return $mail
            ->action(__('View or cancel booking'), $b->manageUrl())
            ->salutation(__('Kind regards,')."\n".Setting::businessName());
    }
}
