<?php

namespace App\Http\Presenters;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingEvent;

/** Zgodovina rezervacije za administracijo: kdaj, kdo in kaj se je zgodilo. */
class BookingHistory
{
    /** @return array<int, array{id: int, event: string, at: string, actor: string, text: string, note: ?string}> */
    public static function for(Booking $booking): array
    {
        return $booking->events()
            ->with('user:id,name')
            ->get()
            ->map(fn (BookingEvent $e) => [
                'id' => $e->id,
                'event' => $e->event,
                'at' => $e->created_at->format('Y-m-d\TH:i'),
                'actor' => self::actor($e, $booking),
                'text' => self::text($e),
                'note' => $e->data['reason'] ?? null,
            ])
            ->all();
    }

    private static function actor(BookingEvent $e, Booking $booking): string
    {
        if ($e->user) {
            return $e->user->name;
        }

        // Brez uporabnika: gost oz. stranka prek povezave, ali sistem (opomnik)
        return $e->event === BookingEvent::REMINDER
            ? __('System')
            : $booking->customer_name.' ('.__('customer').')';
    }

    private static function text(BookingEvent $e): string
    {
        $data = $e->data ?? [];
        $status = fn (?string $value) => $value ? (BookingStatus::tryFrom($value)?->label() ?? $value) : '—';

        return match ($e->event) {
            BookingEvent::CREATED => ($data['for_customer'] ?? false)
                ? __('Booking entered for the customer (:status)', ['status' => $status($data['status'] ?? null)])
                : __('Booking submitted (:status)', ['status' => $status($data['status'] ?? null)]),
            BookingEvent::STATUS => __('Status: :from → :to', ['from' => $status($data['from'] ?? null), 'to' => $status($data['to'] ?? null)]),
            BookingEvent::RESOURCE => isset($data['to'])
                ? __('Resource: :from → :to', ['from' => $data['from'] ?? '—', 'to' => $data['to']])
                : __('Resource removed (:from)', ['from' => $data['from'] ?? '—']),
            BookingEvent::NOTES => __('Internal notes updated'),
            BookingEvent::REMINDER => __('Reminder sent to the customer'),
            default => $e->event,
        };
    }
}
