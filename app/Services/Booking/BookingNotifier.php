<?php

namespace App\Services\Booking;

use App\Enums\BookingMode;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BookingReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingForStaff;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * E-poštna obvestila o rezervacijah. Napaka pri pošiljanju ne sme preprečiti rezervacije,
 * zato se samo zabeleži.
 */
class BookingNotifier
{
    public function created(Booking $booking): void
    {
        $this->safely(fn () => Notification::route('mail', $booking->customer_email)
            ->notify((new BookingReceived($booking))->locale($booking->locale ?? config('app.locale'))));

        $this->safely(function () use ($booking) {
            $notification = new NewBookingForStaff($booking);

            if ($email = Setting::get('booking.notify_email')) {
                Notification::route('mail', $email)->notify($notification);
            }

            // Zaposleni, ki je ta vir (npr. frizer), dobi obvestilo o svoji novi stranki
            $staff = $booking->resource?->user;
            if ($staff && $staff->is_active && $staff->email !== $email) {
                $staff->notify($notification);
            }
        });
    }

    /** Obvestilo stranki o potrditvi, zavrnitvi ali odpovedi (če ni odpovedala sama). */
    public function statusChanged(Booking $booking, ?User $actor): void
    {
        $byCustomer = ! $actor?->isStaff();

        $this->safely(fn () => Notification::route('mail', $booking->customer_email)
            ->notify((new BookingStatusChanged($booking, $byCustomer))->locale($booking->locale ?? config('app.locale'))));

        // Odpoved s strani stranke: obvesti podjetje
        if ($byCustomer && ($email = Setting::get('booking.notify_email'))) {
            $this->safely(fn () => Notification::route('mail', $email)
                ->notify(new BookingStatusChanged($booking, true, forStaff: true)));
        }
    }

    /** Opis časa rezervacije za e-pošto in sezname. */
    public static function when(Booking $booking): string
    {
        $start = $booking->starts_at;
        $end = $booking->ends_at;

        if ($booking->type?->booking_mode === BookingMode::Nightly) {
            return $start->format('d.m.Y H:i').' – '.$end->format('d.m.Y H:i');
        }

        return $start->isSameDay($end)
            ? $start->format('d.m.Y').', '.$start->format('H:i').'–'.$end->format('H:i')
            : $start->format('d.m.Y H:i').' – '.$end->format('d.m.Y H:i');
    }

    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
