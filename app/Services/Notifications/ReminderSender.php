<?php

namespace App\Services\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\ResourceType;
use App\Notifications\BookingReminder;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Opomniki pred začetkom potrjenih rezervacij. Vsak tip vira ima svoj čas opomnika
 * (reminder_hours, null = brez). Rezervacija, oddana že znotraj tega časa, opomnika ne dobi.
 */
class ReminderSender
{
    /** @return int število poslanih opomnikov */
    public function send(): int
    {
        $sent = 0;

        foreach (ResourceType::whereNotNull('reminder_hours')->where('reminder_hours', '>', 0)->get() as $type) {
            $bookings = Booking::with(['type', 'resource', 'service', 'timeslot'])
                ->where('resource_type_id', $type->id)
                ->where('status', BookingStatus::Confirmed)
                ->whereNull('reminder_sent_at')
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', now()->addHours($type->reminder_hours))
                ->get();

            foreach ($bookings as $booking) {
                // Atomarno označi — vzporeden zagon ukaza ne pošlje dvakrat
                $claimed = Booking::whereKey($booking->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                if (! $claimed) {
                    continue;
                }

                // Rezervirano tik pred začetkom: potrditev je dovolj, opomnik bi bil odveč
                if ($booking->created_at->gt($booking->starts_at->copy()->subHours($type->reminder_hours))) {
                    continue;
                }

                try {
                    Notification::route('mail', $booking->customer_email)
                        ->notify((new BookingReminder($booking))->locale($booking->locale ?? config('app.locale')));
                    $booking->recordEvent(BookingEvent::REMINDER);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $sent;
    }
}
