<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Setting;
use App\Notifications\BookingReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingForStaff;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class ManageBookingTest extends BookingTestCase
{
    private function stay(array $typeAttributes = []): Booking
    {
        $type = $this->makeType('nightly', $typeAttributes + ['check_in_time' => '15:00', 'check_out_time' => '10:00', 'cancellation_hours' => 48]);
        $house = $this->makeResource($type, ['price' => 100]);

        $this->post(route('book.store', $type), $this->guest(['resource_id' => $house->id, 'start_date' => '2026-11-26', 'end_date' => '2026-11-28']))
            ->assertSessionHasNoErrors();

        return Booking::sole();
    }

    public function test_guest_cancels_with_token_before_deadline(): void
    {
        $booking = $this->stay();

        $this->post(route('bookings.cancel', $booking), ['token' => 'napacen'])->assertForbidden();
        $this->post(route('bookings.cancel', $booking), ['token' => $booking->access_token])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $booking->fresh()->status->value);
    }

    public function test_cancellation_deadline_blocks_customer_but_not_staff(): void
    {
        $booking = $this->stay();
        // 26. 11. ob 15:00 je manj kot 48 ur po 25. 11. ob 9:00
        $this->travelTo($this->now->copy()->addDays(2));

        $this->post(route('bookings.cancel', $booking), ['token' => $booking->access_token])->assertSessionHasErrors('booking');

        $this->actingAs($this->staff())
            ->post(route('admin.bookings.status', $booking), ['status' => 'cancelled', 'reason' => 'Neurje'])
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $booking->fresh()->status->value);
        Notification::assertSentTo(new AnonymousNotifiable, BookingStatusChanged::class);
    }

    public function test_cancelled_stay_frees_the_dates(): void
    {
        $booking = $this->stay();
        $this->post(route('bookings.cancel', $booking), ['token' => $booking->access_token]);

        $this->post(route('book.store', $booking->type), $this->guest(['customer_email' => 'nova@example.com', 'resource_id' => $booking->resource_id, 'start_date' => '2026-11-26', 'end_date' => '2026-11-28']))
            ->assertSessionHasNoErrors();
    }

    public function test_approval_flow_and_notifications(): void
    {
        Setting::set('booking.notify_email', 'recepcija@example.com');
        $booking = $this->stay(['requires_approval' => true]);

        $this->assertSame('pending', $booking->status->value);
        Notification::assertSentTo(new AnonymousNotifiable, BookingReceived::class);
        Notification::assertSentTo(new AnonymousNotifiable, NewBookingForStaff::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'recepcija@example.com');

        $this->actingAs($this->staff())->post(route('admin.bookings.status', $booking), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $booking->fresh()->status->value);
        $this->assertNotNull($booking->fresh()->confirmed_at);

        // Potrjene ni mogoče zavrniti
        $this->actingAs($this->staff())->post(route('admin.bookings.status', $booking), ['status' => 'rejected'])->assertSessionHasErrors('status');
    }

    public function test_owner_sees_booking_without_token_and_others_do_not(): void
    {
        $type = $this->makeType('nightly', ['check_in_time' => '15:00', 'check_out_time' => '10:00']);
        $house = $this->makeResource($type);
        $owner = $this->customer();

        $this->actingAs($owner)->post(route('book.store', $type), ['resource_id' => $house->id, 'start_date' => '2026-11-26', 'end_date' => '2026-11-27']);
        $booking = Booking::sole();
        $this->assertSame($owner->email, $booking->customer_email);

        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk();
        $this->actingAs($this->customer())->get(route('bookings.show', $booking))->assertNotFound();
        $this->actingAs($owner)->get(route('my-bookings'))->assertOk();
    }

    public function test_staff_assigns_a_free_resource(): void
    {
        $type = $this->makeType('nightly', ['check_in_time' => '15:00', 'check_out_time' => '10:00', 'resource_selection' => 'auto']);
        $a = $this->makeResource($type, ['name' => 'A']);
        $b = $this->makeResource($type, ['name' => 'B']);

        $this->post(route('book.store', $type), $this->guest(['start_date' => '2026-11-26', 'end_date' => '2026-11-28']));
        $this->post(route('book.store', $type), $this->guest(['customer_email' => 'z@example.com', 'start_date' => '2026-11-26', 'end_date' => '2026-11-28']));
        [$first, $second] = Booking::orderBy('id')->get();
        $this->assertSame([$a->id, $b->id], [$first->resource_id, $second->resource_id]);

        // B je zaseden z drugo rezervacijo
        $this->actingAs($this->staff())->patch(route('admin.bookings.update', $first), ['resource_id' => $b->id])->assertSessionHasErrors('resource_id');

        $second->update(['status' => 'cancelled']);
        $this->actingAs($this->staff())->patch(route('admin.bookings.update', $first), ['resource_id' => $b->id])->assertSessionHasNoErrors();
        $this->assertSame($b->id, $first->fresh()->resource_id);
    }
}
