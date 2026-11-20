<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;

class HourlyBookingTest extends BookingTestCase
{
    private function setUpCourts(array $attributes = [], int $capacity = 1): array
    {
        $type = $this->makeType('hourly', $attributes + [
            'slot_interval_minutes' => 60,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 180,
        ]);
        $court = $this->makeResource($type, ['name' => 'Igrišče 1', 'price' => 15, 'capacity' => $capacity]);
        $this->openEveryDay($type, '08:00', '20:00');

        return [$type, $court];
    }

    private function book($type, $court, string $from, string $to, string $email = 'ana@example.com')
    {
        return $this->post(route('book.store', $type), $this->guest([
            'customer_email' => $email, 'resource_id' => $court->id, 'date' => '2026-11-25', 'start_time' => $from, 'end_time' => $to,
        ]));
    }

    public function test_booking_within_hours_with_hourly_price(): void
    {
        [$type, $court] = $this->setUpCourts();

        $this->book($type, $court, '10:00', '12:00')->assertSessionHasNoErrors();
        $this->assertEquals(30, Booking::sole()->total_price);
    }

    public function test_overlap_duration_and_hours_rules(): void
    {
        [$type, $court] = $this->setUpCourts();

        $this->book($type, $court, '10:00', '12:00', 'a@example.com')->assertSessionHasNoErrors();
        $this->book($type, $court, '11:00', '13:00', 'b@example.com')->assertSessionHasErrors('start_time');
        $this->book($type, $court, '12:00', '13:00', 'c@example.com')->assertSessionHasNoErrors();
        $this->book($type, $court, '14:00', '18:00', 'd@example.com')->assertSessionHasErrors('end_time'); // največ 3 h
        $this->book($type, $court, '14:30', '15:30', 'e@example.com')->assertSessionHasNoErrors(); // poravnava ni zahtevana, le večkratnik koraka
        $this->book($type, $court, '19:00', '21:00', 'f@example.com')->assertSessionHasErrors('start_time'); // po zaprtju

        $this->assertSame(3, Booking::count());
    }

    public function test_capacity_allows_concurrent_bookings(): void
    {
        [$type, $court] = $this->setUpCourts(capacity: 2);

        $this->book($type, $court, '10:00', '12:00', 'a@example.com')->assertSessionHasNoErrors();
        $this->book($type, $court, '11:00', '13:00', 'b@example.com')->assertSessionHasNoErrors();
        $this->book($type, $court, '11:00', '12:00', 'c@example.com')->assertSessionHasErrors('start_time');
        // 12–14: rezervacija a se konča ob 12:00, zato je hkrati zasedena samo b
        $this->book($type, $court, '12:00', '14:00', 'd@example.com')->assertSessionHasNoErrors();
        // 12–13: b in d hkrati → polno
        $this->book($type, $court, '12:00', '13:00', 'e@example.com')->assertSessionHasErrors('start_time');
        $this->book($type, $court, '13:00', '14:00', 'f@example.com')->assertSessionHasNoErrors();
    }

    public function test_availability_reports_max_end_per_start(): void
    {
        [$type, $court] = $this->setUpCourts();
        $this->book($type, $court, '12:00', '13:00');

        $starts = collect($this->getJson(route('book.availability', [$type, 'date' => '2026-11-25']))
            ->assertOk()
            ->json('resources.0.starts'))->pluck('max_end', 'time');

        $this->assertSame('11:00', $starts['08:00']); // največ 3 h
        $this->assertSame('12:00', $starts['10:00']);
        $this->assertArrayNotHasKey('12:00', $starts->all());
        $this->assertSame('16:00', $starts['13:00']);
        $this->assertSame('20:00', $starts['19:00']);
    }
}
