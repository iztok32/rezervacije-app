<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Presets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_pages_are_reachable(): void
    {
        $type = Presets::apply('hairdresser');

        $this->get('/')->assertOk();
        $this->get(route('book.show', $type))->assertOk();
    }

    public function test_inactive_type_is_hidden_from_customers(): void
    {
        $type = Presets::apply('court');
        $type->update(['is_active' => false]);

        $this->get(route('book.show', $type))->assertNotFound();
        $this->actingAs(User::factory()->staff()->create())->get(route('book.show', $type))->assertOk();
    }

    public function test_customers_cannot_access_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.bookings.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.resource-types.index'))->assertForbidden();
    }

    public function test_staff_manages_bookings_but_not_catalog(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.bookings.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.calendar'))->assertOk();
        $this->actingAs($staff)->get(route('admin.resource-types.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $type = Presets::apply('riding');

        foreach ([
            route('dashboard'),
            route('admin.bookings.index'),
            route('admin.calendar', ['view' => 'timeline']),
            route('admin.resource-types.index'),
            route('admin.resource-types.create'),
            route('admin.resource-types.edit', $type),
            route('admin.closures.index'),
            route('admin.users.index'),
            route('admin.settings.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_creates_type_from_preset(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.resource-types.store'), ['preset' => 'glamping', 'with_samples' => true, 'name' => 'Hiške ob jezeru'])
            ->assertRedirect();

        $this->assertDatabaseHas('resource_types', ['name' => 'Hiške ob jezeru', 'slug' => 'hiske-ob-jezeru', 'booking_mode' => 'nightly']);
        $this->assertDatabaseCount('resources', 2);
    }
}
