<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Timeslot;

class TimeslotBookingTest extends BookingTestCase
{
    private function setUpRiding(array $typeAttributes = [], array $slotAttributes = []): array
    {
        $type = $this->makeType('timeslot', $typeAttributes + ['allow_guests' => false]);
        $bella = $this->makeResource($type, ['name' => 'Bella']);
        $luna = $this->makeResource($type, ['name' => 'Luna']);

        // Torek in četrtek 17–18, 2 mesti
        $slot = Timeslot::create($slotAttributes + [
            'resource_type_id' => $type->id,
            'name' => 'Začetniki',
            'weekdays' => [2, 4],
            'start_time' => '17:00',
            'end_time' => '18:00',
            'capacity' => 2,
            'price' => 25,
        ]);

        return [$type, $slot, $bella, $luna];
    }

    public function test_customer_books_a_session_with_a_horse(): void
    {
        [$type, $slot, $bella] = $this->setUpRiding();
        $user = $this->customer();

        $this->actingAs($user)
            ->post(route('book.store', $type), ['timeslot_id' => $slot->id, 'date' => '2026-11-24', 'resource_id' => $bella->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame($user->id, $booking->user_id);
        $this->assertSame('2026-11-24 17:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame($bella->id, $booking->resource_id);
        $this->assertEquals(25, $booking->total_price);
    }

    public function test_session_must_take_place_on_the_date(): void
    {
        [$type, $slot, $bella] = $this->setUpRiding();

        // Sreda — termin poteka samo ob torkih in četrtkih
        $this->actingAs($this->customer())
            ->post(route('book.store', $type), ['timeslot_id' => $slot->id, 'date' => '2026-11-25', 'resource_id' => $bella->id])
            ->assertSessionHasErrors('timeslot_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_capacity_and_horse_double_booking_are_enforced(): void
    {
        [$type, $slot, $bella, $luna] = $this->setUpRiding();
        $data = ['timeslot_id' => $slot->id, 'date' => '2026-11-24'];

        $this->actingAs($this->customer())->post(route('book.store', $type), $data + ['resource_id' => $bella->id])->assertSessionHasNoErrors();

        // Bella je že zasedena
        $this->actingAs($this->customer())->post(route('book.store', $type), $data + ['resource_id' => $bella->id])->assertSessionHasErrors('resource_id');

        $this->actingAs($this->customer())->post(route('book.store', $type), $data + ['resource_id' => $luna->id])->assertSessionHasNoErrors();

        // Kapaciteta 2 je polna
        $third = $this->makeResource($type, ['name' => 'Max']);
        $this->actingAs($this->customer())->post(route('book.store', $type), $data + ['resource_id' => $third->id])->assertSessionHasErrors('timeslot_id');

        $this->assertSame(2, Booking::count());
    }

    public function test_same_customer_cannot_book_the_same_session_twice(): void
    {
        [$type, $slot, $bella, $luna] = $this->setUpRiding();
        $user = $this->customer();
        $data = ['timeslot_id' => $slot->id, 'date' => '2026-11-24'];

        $this->actingAs($user)->post(route('book.store', $type), $data + ['resource_id' => $bella->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('book.store', $type), $data + ['resource_id' => $luna->id])->assertSessionHasErrors('booking');
    }

    public function test_guests_must_log_in_when_type_requires_it(): void
    {
        [$type, $slot, $bella] = $this->setUpRiding();

        $this->post(route('book.store', $type), $this->guest(['timeslot_id' => $slot->id, 'date' => '2026-11-24', 'resource_id' => $bella->id]))
            ->assertSessionHasErrors('auth');
    }

    public function test_advance_window_applies_to_customers_but_not_staff(): void
    {
        [$type, $slot, $bella] = $this->setUpRiding(['max_advance_days' => 7]);
        $data = ['timeslot_id' => $slot->id, 'date' => '2026-12-08', 'resource_id' => $bella->id];

        $this->actingAs($this->customer())->post(route('book.store', $type), $data)->assertSessionHasErrors('date');

        $this->actingAs($this->staff())
            ->post(route('book.store', $type), $data + ['customer_name' => 'Telefonska stranka', 'customer_email' => 'tel@example.com'])
            ->assertSessionHasNoErrors();
    }

    public function test_session_in_the_past_cannot_be_booked(): void
    {
        [$type, $slot, $bella] = $this->setUpRiding(slotAttributes: ['weekdays' => [1], 'start_time' => '08:00', 'end_time' => '09:00']);

        // Danes (ponedeljek) ob 8:00 — že mimo
        $this->actingAs($this->customer())
            ->post(route('book.store', $type), ['timeslot_id' => $slot->id, 'date' => '2026-11-23', 'resource_id' => $bella->id])
            ->assertSessionHasErrors('date');
    }

    public function test_auto_selection_assigns_a_free_resource(): void
    {
        [$type, $slot, $bella, $luna] = $this->setUpRiding(['resource_selection' => 'auto']);
        $data = ['timeslot_id' => $slot->id, 'date' => '2026-11-24'];

        $this->actingAs($this->customer())->post(route('book.store', $type), $data)->assertSessionHasNoErrors();
        $this->actingAs($this->customer())->post(route('book.store', $type), $data)->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$bella->id, $luna->id], Booking::pluck('resource_id')->all());
    }

    public function test_staff_selection_leaves_resource_unassigned_and_requires_approval(): void
    {
        [$type, $slot] = $this->setUpRiding(['resource_selection' => 'staff', 'requires_approval' => true]);

        $this->actingAs($this->customer())
            ->post(route('book.store', $type), ['timeslot_id' => $slot->id, 'date' => '2026-11-24'])
            ->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertNull($booking->resource_id);
        $this->assertSame('pending', $booking->status->value);
    }

    public function test_availability_lists_occurrences_with_remaining_places(): void
    {
        [$type, $slot, $bella] = $this->setUpRiding();
        $this->actingAs($this->customer())->post(route('book.store', $type), ['timeslot_id' => $slot->id, 'date' => '2026-11-24', 'resource_id' => $bella->id]);

        $response = $this->getJson(route('book.availability', [$type, 'from' => '2026-11-23', 'to' => '2026-11-29']))->assertOk();

        $occurrences = collect($response->json('occurrences'));
        $this->assertSame(['2026-11-24', '2026-11-26'], $occurrences->pluck('date')->all());
        $this->assertSame(1, $occurrences[0]['remaining']);
        $this->assertFalse(collect($occurrences[0]['resources'])->firstWhere('id', $bella->id)['available']);
    }
}
