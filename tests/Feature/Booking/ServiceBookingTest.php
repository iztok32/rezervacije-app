<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Closure;
use App\Models\Service;

class ServiceBookingTest extends BookingTestCase
{
    private function setUpSalon(array $typeAttributes = []): array
    {
        $type = $this->makeType('service', $typeAttributes + ['resource_selection' => 'auto', 'slot_interval_minutes' => 30]);
        $maja = $this->makeResource($type, ['name' => 'Maja']);
        $tina = $this->makeResource($type, ['name' => 'Tina']);
        $this->openEveryDay($type, '08:00', '12:00');

        $cut = Service::create([
            'resource_type_id' => $type->id,
            'name' => 'Striženje',
            'duration_minutes' => 60,
            'buffer_minutes' => 30,
            'price' => 30,
        ]);

        return [$type, $cut, $maja, $tina];
    }

    private function slots($type, $service, string $date, ?int $resourceId = null): array
    {
        $days = $this->getJson(route('book.availability', [$type, 'service_id' => $service->id, 'from' => $date, 'to' => $date, 'resource_id' => $resourceId]))
            ->assertOk()
            ->json('days');

        return collect($days[0]['slots'])->pluck('time')->all();
    }

    public function test_slots_follow_working_hours_and_duration(): void
    {
        [$type, $cut] = $this->setUpSalon();

        // 8:00–12:00, 60 min, korak 30 → zadnji začetek 11:00
        $this->assertSame(['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00'], $this->slots($type, $cut, '2026-11-24'));
    }

    public function test_booking_blocks_the_slot_including_buffer(): void
    {
        [$type, $cut, $maja] = $this->setUpSalon(['resource_selection' => 'customer']);

        $this->post(route('book.store', $type), $this->guest(['service_id' => $cut->id, 'resource_id' => $maja->id, 'date' => '2026-11-24', 'time' => '09:00']))
            ->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame('2026-11-24 10:00', $booking->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-24 10:30', $booking->blocks_until->format('Y-m-d H:i'));

        // Zasedeno 9:00–10:30: začetki od 8:30 do 10:00 odpadejo (8:30 + 60 + 30 > 9:00)
        $this->assertSame(['10:30', '11:00'], $this->slots($type, $cut, '2026-11-24', $maja->id));

        $this->post(route('book.store', $type), $this->guest(['customer_email' => 'b@example.com', 'service_id' => $cut->id, 'resource_id' => $maja->id, 'date' => '2026-11-24', 'time' => '10:00']))
            ->assertSessionHasErrors('time');
    }

    public function test_any_staff_member_gets_the_booking_when_one_is_busy(): void
    {
        [$type, $cut, $maja, $tina] = $this->setUpSalon();
        $data = ['service_id' => $cut->id, 'date' => '2026-11-24', 'time' => '09:00'];

        $this->post(route('book.store', $type), $this->guest($data))->assertSessionHasNoErrors();
        $this->post(route('book.store', $type), $this->guest($data + ['customer_email' => 'b@example.com']))->assertSessionHasNoErrors();
        $this->post(route('book.store', $type), $this->guest($data + ['customer_email' => 'c@example.com']))->assertSessionHasErrors('time');

        $this->assertEqualsCanonicalizing([$maja->id, $tina->id], Booking::pluck('resource_id')->all());
    }

    public function test_resource_specific_hours_override_type_hours(): void
    {
        [$type, $cut, $maja] = $this->setUpSalon(['resource_selection' => 'customer']);
        // Maja dela samo popoldne
        $this->openEveryDay($type, '14:00', '16:00', $maja);

        $this->assertSame(['14:00', '14:30', '15:00'], $this->slots($type, $cut, '2026-11-24', $maja->id));
    }

    public function test_closure_removes_slots(): void
    {
        [$type, $cut, $maja] = $this->setUpSalon(['resource_selection' => 'customer']);
        Closure::create(['resource_id' => $maja->id, 'resource_type_id' => $type->id, 'starts_at' => '2026-11-24 00:00', 'ends_at' => '2026-11-25 00:00', 'reason' => 'Dopust']);

        $this->assertSame([], $this->slots($type, $cut, '2026-11-24', $maja->id));
        $this->assertNotEmpty($this->slots($type, $cut, '2026-11-25', $maja->id));
    }

    public function test_service_limited_to_specific_staff(): void
    {
        [$type, $cut, $maja, $tina] = $this->setUpSalon(['resource_selection' => 'customer']);
        $cut->resources()->sync([$tina->id]);

        $this->post(route('book.store', $type), $this->guest(['service_id' => $cut->id, 'resource_id' => $maja->id, 'date' => '2026-11-24', 'time' => '09:00']))
            ->assertSessionHasErrors('resource_id');
    }

    public function test_min_advance_minutes(): void
    {
        [$type, $cut] = $this->setUpSalon(['min_advance_minutes' => 120]);

        // Zdaj je 9:00 ponedeljek → prvi možen začetek 11:00
        $this->assertSame(['11:00'], $this->slots($type, $cut, '2026-11-23'));
    }
}
