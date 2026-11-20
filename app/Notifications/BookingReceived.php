<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Stranki: rezervacija je prejeta (čaka potrditev) ali potrjena. */
class BookingReceived extends Notification
{
    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking->loadMissing(['type', 'resource', 'service', 'timeslot']);
        $pending = $b->status === BookingStatus::Pending;

        $mail = (new MailMessage)
            ->subject(($pending ? __('Booking received') : __('Booking confirmed')).' · '.$b->reference.' · '.Setting::businessName())
            ->greeting(__('Hello :name,', ['name' => $b->customer_name]))
            ->line($pending
                ? __('Thank you for your booking. We will confirm it as soon as possible.')
                : __('Your booking is confirmed.'));

        return BookingMailDetails::append($mail, $b)
            ->action(__('View or cancel booking'), $b->manageUrl())
            ->salutation(__('Kind regards,')."\n".Setting::businessName());
    }
}
