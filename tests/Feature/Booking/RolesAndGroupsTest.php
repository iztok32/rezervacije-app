<?php

namespace Tests\Feature\Booking;

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\CustomerGroup;
use App\Models\ResourceType;
use App\Models\Role;
use App\Models\Timeslot;
use App\Models\User;

class RolesAndGroupsTest extends BookingTestCase
{
    /** Termin s konjema Bella (vodi ga $provider) in Luna; ena rezervacija na vsakega. */
    private function ridingWithBookings(User $provider): array
    {
        $type = $this->makeType('timeslot');
        $bella = $this->makeResource($type, ['name' => 'Bella', 'user_id' => $provider->id]);
        $luna = $this->makeResource($type, ['name' => 'Luna']);
        $slot = $this->slot($type);

        foreach ([$bella, $luna] as $i => $horse) {
            $this->post(route('book.store', $type), $this->guest([
                'customer_email' => "gost{$i}@example.com",
                'timeslot_id' => $slot->id,
                'date' => '2026-11-24',
                'resource_id' => $horse->id,
            ]))->assertSessionHasNoErrors();
        }

        return [
            $type,
            Booking::where('resource_id', $bella->id)->sole(),
            Booking::where('resource_id', $luna->id)->sole(),
        ];
    }

    private function slot(ResourceType $type, array $attributes = []): Timeslot
    {
        return Timeslot::create($attributes + [
            'resource_type_id' => $type->id,
            'name' => 'Začetniki',
            'weekdays' => [2, 4],
            'start_time' => '17:00',
            'end_time' => '18:00',
            'capacity' => 4,
        ]);
    }

    private function role(array $permissions, array $attributes = []): Role
    {
        return Role::create($attributes + ['name' => 'Vloga '.uniqid(), 'permissions' => array_map(fn (Permission $p) => $p->value, $permissions)]);
    }

    // ── Pravice in doseg ──

    public function test_provider_sees_and_manages_only_own_bookings(): void
    {
        $maja = User::factory()->provider()->create();
        [, $own, $other] = $this->ridingWithBookings($maja);

        $this->actingAs($maja)->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('bookings.data', 1)->where('bookings.data.0.reference', $own->reference));

        $this->actingAs($maja)->get(route('admin.bookings.show', $own))->assertOk();
        $this->actingAs($maja)->get(route('admin.bookings.show', $other))->assertForbidden();

        $this->actingAs($maja)->post(route('admin.bookings.status', $own), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->actingAs($maja)->post(route('admin.bookings.status', $other), ['status' => 'cancelled'])->assertForbidden();
        $this->assertSame('confirmed', $other->fresh()->status->value);

        // Brez pravice za rezervacije za stranke in brez dostopa do ponudbe
        $this->actingAs($maja)->get(route('admin.customers.search', ['q' => 'ana']))->assertForbidden();
        $this->actingAs($maja)->get(route('admin.resource-types.index'))->assertForbidden();
    }

    public function test_timeslot_staff_counts_as_own(): void
    {
        $coach = User::factory()->provider()->create();
        [$type, , $other] = $this->ridingWithBookings(User::factory()->create());
        Timeslot::where('resource_type_id', $type->id)->sole()->staff()->attach($coach);

        $this->actingAs($coach)->get(route('admin.bookings.show', $other))->assertOk();
    }

    public function test_role_limited_to_resource_types(): void
    {
        [$riding, $booking] = $this->ridingWithBookings(User::factory()->create());
        $role = $this->role([Permission::BookingsView]);
        $role->resourceTypes()->attach($this->makeType('nightly'));
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get(route('admin.bookings.index'))
            ->assertInertia(fn ($page) => $page->has('bookings.data', 0)->has('types', 1));
        $this->actingAs($user)->get(route('admin.bookings.show', $booking))->assertForbidden();

        $role->resourceTypes()->sync([$riding->id]);
        $this->actingAs($user->fresh())->get(route('admin.bookings.show', $booking))->assertOk();
    }

    public function test_each_admin_section_requires_its_permission(): void
    {
        $user = User::factory()->create(['role_id' => $this->role([Permission::OfferManage])->id]);

        $this->actingAs($user)->get(route('admin.resource-types.index'))->assertOk();
        foreach (['admin.bookings.index', 'admin.calendar', 'admin.closures.index', 'admin.users.index', 'admin.roles.index', 'admin.customer-groups.index', 'admin.settings.index'] as $name) {
            $this->actingAs($user)->get(route($name))->assertForbidden();
        }

        // Brez pravice za ogled rezervacij dobi pregled stranke
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_view_only_staff_cannot_change_bookings(): void
    {
        [, $booking] = $this->ridingWithBookings(User::factory()->create());
        $user = User::factory()->create(['role_id' => $this->role([Permission::BookingsView])->id]);

        $this->actingAs($user)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false));
        $this->actingAs($user)->post(route('admin.bookings.status', $booking), ['status' => 'cancelled'])->assertForbidden();
    }

    public function test_shared_props_expose_permissions(): void
    {
        $this->actingAs(User::factory()->provider()->create())->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.is_admin', false)
                ->where('auth.user.permissions', ['bookings.view', 'bookings.manage']));

        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.user.permissions', Permission::values()));
    }

    // ── Upravljanje uporabnikov in vlog ──

    public function test_user_manager_without_roles_permission_cannot_assign_roles(): void
    {
        $manager = User::factory()->create(['role_id' => $this->role([Permission::UsersManage])->id]);
        $customer = $this->customer();
        $group = CustomerGroup::create(['name' => 'Člani']);

        // Podatke in skupine lahko ureja …
        $this->actingAs($manager)->put(route('admin.users.update', $customer), [
            'name' => 'Novo ime', 'email' => $customer->email, 'is_active' => true, 'customer_group_ids' => [$group->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$group->id], $customer->fresh()->customerGroupIds());

        // … vloge pa ne
        $this->actingAs($manager)->put(route('admin.users.update', $customer), [
            'name' => 'Novo ime', 'email' => $customer->email, 'is_active' => true, 'role_id' => Role::bySlug('reception')->id,
        ])->assertSessionHasErrors('role_id');
        $this->assertNull($customer->fresh()->role_id);
    }

    public function test_only_admin_assigns_admin_role_or_edits_admins(): void
    {
        $manager = User::factory()->create(['role_id' => $this->role([Permission::UsersManage, Permission::RolesManage])->id]);
        $customer = $this->customer();
        $admin = $this->admin();

        $this->actingAs($manager)->put(route('admin.users.update', $customer), [
            'name' => $customer->name, 'email' => $customer->email, 'is_active' => true, 'role_id' => Role::admin()->id,
        ])->assertSessionHasErrors('role_id');

        $this->actingAs($manager)->put(route('admin.users.update', $customer), [
            'name' => $customer->name, 'email' => $customer->email, 'is_active' => true, 'role_id' => Role::bySlug('provider')->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'is_active' => false, 'role_id' => $admin->role_id,
        ])->assertSessionHasErrors('role_id');
        $this->actingAs($manager)->delete(route('admin.users.destroy', $admin));
        $this->assertModelExists($admin);
    }

    public function test_user_cannot_change_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'is_active' => true, 'role_id' => null,
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_roles_crud_protects_admin_role_and_assigned_roles(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Inštruktor', 'permissions' => ['bookings.view', 'bookings.manage'], 'booking_scope' => 'own',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['bookings.view', 'bookings.manage'], Role::where('name', 'Inštruktor')->sole()->permissions);

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Napačna', 'permissions' => ['everything'], 'booking_scope' => 'all',
        ])->assertSessionHasErrors('permissions.0');

        $this->actingAs($admin)->put(route('admin.roles.update', Role::admin()), [
            'name' => 'Admin', 'permissions' => [], 'booking_scope' => 'all',
        ])->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.roles.destroy', Role::admin()))->assertSessionHas('error');

        User::factory()->provider()->create();
        $this->actingAs($admin)->delete(route('admin.roles.destroy', Role::bySlug('provider')))->assertSessionHas('error');
        $this->assertNotNull(Role::bySlug('provider'));
    }

    // ── Skupine strank ──

    public function test_offer_restricted_to_customer_group(): void
    {
        $type = $this->makeType('timeslot');
        $slot = $this->slot($type);
        $members = CustomerGroup::create(['name' => 'Člani']);
        $type->customerGroups()->attach($members);

        $member = $this->customer();
        $member->customerGroups()->attach($members);
        $outsider = $this->customer();
        $booking = ['timeslot_id' => $slot->id, 'date' => '2026-11-24'];

        // Gost: preusmeritev na prijavo, oddaja zavrnjena
        $this->get(route('book.show', $type))->assertRedirect(route('login'));
        $this->post(route('book.store', $type), $this->guest($booking))->assertSessionHasErrors('auth');

        // Nečlan: ponudba skrita in nedostopna
        $this->actingAs($outsider)->get(route('home'))->assertInertia(fn ($page) => $page->has('types', 0));
        $this->actingAs($outsider)->get(route('book.show', $type))->assertForbidden();
        $this->actingAs($outsider)->post(route('book.store', $type), $booking)->assertSessionHasErrors('resource_type');

        // Član in recepcija (rezervira za stranko)
        $this->actingAs($member)->get(route('home'))->assertInertia(fn ($page) => $page->has('types', 1));
        $this->actingAs($member)->post(route('book.store', $type), $booking)->assertSessionHasNoErrors();
        $this->actingAs($this->staff())->get(route('book.show', $type))->assertOk();

        $this->assertSame(1, Booking::count());
    }

    public function test_timeslot_restricted_to_customer_group(): void
    {
        $type = $this->makeType('timeslot');
        $open = $this->slot($type, ['name' => 'Začetniki']);
        $advanced = $this->slot($type, ['name' => 'Napredni', 'start_time' => '18:00', 'end_time' => '19:00']);
        $group = CustomerGroup::create(['name' => 'Napredni jahači']);
        $advanced->customerGroups()->attach($group);

        $member = $this->customer();
        $member->customerGroups()->attach($group);
        $query = ['from' => '2026-11-24', 'to' => '2026-11-24'];

        $names = fn (?User $user) => collect(($user ? $this->actingAs($user) : $this)
            ->getJson(route('book.availability', $type).'?'.http_build_query($query))
            ->json('occurrences'))->pluck('name')->all();

        $this->assertSame(['Začetniki'], $names($this->customer()));
        $this->assertSame(['Začetniki', 'Napredni'], $names($member));

        $this->actingAs($this->customer())
            ->post(route('book.store', $type), ['timeslot_id' => $advanced->id, 'date' => '2026-11-24'])
            ->assertSessionHasErrors('resource_type');
        $this->actingAs($member)
            ->post(route('book.store', $type), ['timeslot_id' => $advanced->id, 'date' => '2026-11-24'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($open);
    }

    public function test_group_in_use_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $group = CustomerGroup::create(['name' => 'Člani']);
        $type = $this->makeType('nightly');
        $type->customerGroups()->attach($group);

        $this->actingAs($admin)->delete(route('admin.customer-groups.destroy', $group))->assertSessionHas('error');
        $this->assertModelExists($group);

        $type->customerGroups()->detach();
        $this->actingAs($admin)->delete(route('admin.customer-groups.destroy', $group))->assertSessionHas('success');
        $this->assertModelMissing($group);
    }

    public function test_admin_sets_group_restriction_on_type_and_timeslot(): void
    {
        $admin = $this->admin();
        $group = CustomerGroup::create(['name' => 'Člani']);
        $type = $this->makeType('timeslot');
        $slot = $this->slot($type);

        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.customer-groups.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.resource-types.edit', $type))
            ->assertInertia(fn ($page) => $page->has('customerGroups', 1)->where('type.customer_group_ids', []));

        $this->actingAs($admin)->put(route('admin.timeslots.update', $slot), [
            'name' => $slot->name, 'weekdays' => [2], 'start_time' => '17:00', 'end_time' => '18:00',
            'customer_group_ids' => [$group->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($slot->fresh()->isRestricted());
        $this->assertFalse($slot->fresh()->isOpenTo(null));
    }
}
