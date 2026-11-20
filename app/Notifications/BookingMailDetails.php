<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Setting;
use App\Services\Booking\BookingNotifier;
use Illuminate\Notifications\Messages\MailMessage;

/** Skupni povzetek rezervacije v e-poštnih sporočilih. */
class BookingMailDetails
{
    public static function append(MailMessage $mail, Booking $b, bool $withCustomer = false): MailMessage
    {
        $lines = [
            __('Reference').': **'.$b->reference.'**',
            __('Booking').': '.$b->title(),
            __('When').': '.BookingNotifier::when($b),
        ];

        if ($b->quantity > 1) {
            $lines[] = __('Quantity').': '.$b->quantity;
        }
        if ($b->total_price !== null) {
            $lines[] = __('Price').': '.number_format((float) $b->total_price, 2, ',', '.').' '.Setting::get('business.currency', 'EUR');
        }
        if ($withCustomer) {
            $lines[] = __('Customer').': '.$b->customer_name.' ('.$b->customer_email.($b->customer_phone ? ', '.$b->customer_phone : '').')';
        }
        if ($b->notes) {
            $lines[] = __('Notes').': '.$b->notes;
        }

        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
