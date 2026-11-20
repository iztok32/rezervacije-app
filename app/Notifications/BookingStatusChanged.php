<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Stranki (ali podjetju): rezervacija je potrjena, zavrnjena ali odpovedana. */
class BookingStatusChanged extends Notification
{
    public function __construct(
        public Booking $booking,
        public bool $byCustomer = false,
        public bool $forStaff = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $b = $this->booking->loadMissing(['type', 'resource', 'service', 'timeslot']);

        [$subject, $line] = match ($b->status) {
            BookingStatus::Confirmed => [__('Booking confirmed'), __('Your booking is confirmed.')],
            BookingStatus::Rejected => [__('Booking declined'), __('Unfortunately we cannot accept your booking.')],
            BookingStatus::Cancelled => $this->forStaff
                ? [__('Booking cancelled by customer'), __(':name has cancelled their booking.', ['name' => $b->customer_name])]
                : [__('Booking cancelled'), $this->byCustomer ? __('Your booking has been cancelled.') : __('Your booking has been cancelled by us.')],
            default => [__('Booking updated'), __('Your booking has been updated.')],
        };

        $mail = (new MailMessage)
            ->subject($subject.' · '.$b->reference.' · '.Setting::businessName())
            ->greeting($this->forStaff ? __('Hello,') : __('Hello :name,', ['name' => $b->customer_name]))
            ->line($line);

        if ($b->cancellation_reason) {
            $mail->line(__('Reason').': '.$b->cancellation_reason);
        }

        BookingMailDetails::append($mail, $b, withCustomer: $this->forStaff);

        if ($this->forStaff) {
            $mail->action(__('Open booking'), route('admin.bookings.show', $b));
        } elseif ($b->isActive()) {
            $mail->action(__('View or cancel booking'), $b->manageUrl());
        }

        return $mail->salutation(__('Kind regards,')."\n".Setting::businessName());
    }
}
