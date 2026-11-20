<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Podjetju / zaposlenemu: nova rezervacija (po potrebi čaka na potrditev). */
class NewBookingForStaff extends Notification
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
            ->subject(($pending ? __('New booking awaiting confirmation') : __('New booking')).' · '.$b->reference)
            ->greeting(__('Hello,'))
            ->line($pending
                ? __('A new booking is waiting for your confirmation.')
                : __('A new booking has been made.'));

        return BookingMailDetails::append($mail, $b, withCustomer: true)
            ->action(__('Open booking'), route('admin.bookings.show', $b))
            ->salutation(Setting::businessName());
    }
}
