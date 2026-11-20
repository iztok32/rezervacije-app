<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;

class NightlyBookingTest extends BookingTestCase
{
    private function setUpGlamping(array $attributes = []): array
    {
        $type = $this->makeType('nightly', $attributes + [
            'nightly_unit' => 'night',
            'check_in_time' => '15:00',
            'check_out_time' => '10:00',
            'min_units' => 2,
            'max_units' => 7,
        ]);
        $house = $this->makeResource($type, ['name' => 'Gozdna hiška', 'price' => 120, 'max_guests' => 2]);

        return [$type, $house];
    }

    public function test_guest_books_a_stay_and_price_is_per_night(): void
    {
        [$type, $house] = $this->setUpGlamping();

        $response = $this->post(route('book.store', $type), $this->guest([
            'resource_id' => $house->id, 'start_date' => '2026-12-04', 'end_date' => '2026-12-07', 'quantity' => 2,
        ]));

        $booking = Booking::sole();
        $response->assertRedirect($booking->manageUrl());
        $this->assertSame('2026-12-04 15:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-12-07 10:00', $booking->ends_at->format('Y-m-d H:i'));
        $this->assertEquals(360, $booking->total_price);
        $this->assertNull($booking->user_id);

        // Gost s povezavo vidi rezervacijo, brez žetona ne
        $this->get($booking->manageUrl())->assertOk();
        $this->get(route('bookings.show', $booking))->assertNotFound();
    }

    public function test_overlapping_stay_is_rejected_but_back_to_back_is_allowed(): void
    {
        [$type, $house] = $this->setUpGlamping();
        $book = fn ($from, $to, $email) => $this->post(route('book.store', $type), $this->guest([
            'customer_email' => $email, 'resource_id' => $house->id, 'start_date' => $from, 'end_date' => $to,
        ]));

        $book('2026-12-04', '2026-12-07', 'a@example.com')->assertSessionHasNoErrors();
        $book('2026-12-06', '2026-12-09', 'b@example.com')->assertSessionHasErrors('start_date');
        // Odhod ob 10:00, prihod ob 15:00 istega dne
        $book('2026-12-07', '2026-12-09', 'c@example.com')->assertSessionHasNoErrors();
        $book('2026-12-02', '2026-12-04', 'd@example.com')->assertSessionHasNoErrors();

        $this->assertSame(3, Booking::count());
    }

    public function test_minimum_and_maximum_nights_and_guests(): void
    {
        [$type, $house] = $this->setUpGlamping();
        $data = $this->guest(['resource_id' => $house->id]);

        $this->post(route('book.store', $type), $data + ['start_date' => '2026-12-04', 'end_date' => '2026-12-05'])->assertSessionHasErrors('end_date');
        $this->post(route('book.store', $type), $data + ['start_date' => '2026-12-04', 'end_date' => '2026-12-14'])->assertSessionHasErrors('end_date');
        $this->post(route('book.store', $type), $data + ['start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'quantity' => 3])->assertSessionHasErrors('quantity');
    }

    public function test_rental_by_days_counts_both_ends(): void
    {
        $type = $this->makeType('nightly', ['nightly_unit' => 'day', 'check_in_time' => '08:00', 'check_out_time' => '18:00', 'min_units' => 1]);
        $car = $this->makeResource($type, ['name' => 'Golf', 'price' => 45]);

        $this->post(route('book.store', $type), $this->guest(['resource_id' => $car->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-03']))
            ->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertEquals(135, $booking->total_price);
        $this->assertSame('2026-12-03 18:00', $booking->ends_at->format('Y-m-d H:i'));

        // Enodnevni najem na isti dan je mogoč
        $this->post(route('book.store', $type), $this->guest(['customer_email' => 'x@example.com', 'resource_id' => $car->id, 'start_date' => '2026-12-04', 'end_date' => '2026-12-04']))
            ->assertSessionHasNoErrors();
    }

    public function test_availability_marks_booked_nights(): void
    {
        [$type, $house] = $this->setUpGlamping();
        $this->post(route('book.store', $type), $this->guest(['resource_id' => $house->id, 'start_date' => '2026-12-04', 'end_date' => '2026-12-06']));

        $blocked = $this->getJson(route('book.availability', [$type, 'from' => '2026-12-01', 'to' => '2026-12-10']))
            ->assertOk()
            ->json('resources.0.blocked');

        // Zasedeni noči 4. in 5. decembra; 6. je dan odhoda in je spet prost za prihod
        $this->assertSame(['2026-12-04', '2026-12-05'], $blocked);
    }
}
