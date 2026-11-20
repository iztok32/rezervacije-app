<?php

namespace Tests\Feature\Booking;

use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

abstract class BookingTestCase extends TestCase
{
    use RefreshDatabase;

    /** Ponedeljek, 23. 11. 2026, 9:00 */
    protected Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-11-23 09:00:00');
        Carbon::setTestNow($this->now);
        Notification::fake();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeType(string $mode, array $attributes = []): ResourceType
    {
        return ResourceType::create(array_merge([
            'name' => ucfirst($mode),
            'slug' => $mode.'-'.uniqid(),
            'booking_mode' => $mode,
            'resource_selection' => 'customer',
            'allow_guests' => true,
            'cancellation_hours' => 24,
            'max_advance_days' => 60,
        ], $attributes));
    }

    protected function makeResource(ResourceType $type, array $attributes = []): Resource
    {
        return $type->resources()->create(array_merge(['name' => 'Vir '.uniqid()], $attributes));
    }

    /** Delovni čas za vse dni v tednu. */
    protected function openEveryDay(ResourceType $type, string $from = '08:00', string $to = '16:00', ?Resource $resource = null): void
    {
        foreach (range(1, 7) as $day) {
            WorkingHour::create([
                'resource_type_id' => $type->id,
                'resource_id' => $resource?->id,
                'weekday' => $day,
                'start_time' => $from,
                'end_time' => $to,
            ]);
        }
    }

    protected function guest(array $extra = []): array
    {
        return array_merge([
            'customer_name' => 'Ana Novak',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '040 111 222',
        ], $extra);
    }

    protected function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    protected function staff(): User
    {
        return User::factory()->staff()->create();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
