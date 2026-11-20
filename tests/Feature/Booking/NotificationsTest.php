<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\CustomerGroup;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\ResourceType;
use App\Models\Role;
use App\Models\Timeslot;
use App\Models\User;
use App\Notifications\BookingReminder;
use App\Notifications\BookingStatusChanged;
use App\Notifications\CustomerMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class NotificationsTest extends BookingTestCase
{
    /** Termin torek in četrtek 17–18 (prvi: torek 24. 11. 2026). */
    private function riding(array $typeAttributes = []): array
    {
        $type = $this->makeType('timeslot', $typeAttributes + ['resource_selection' => 'auto']);
        $horse = $this->makeResource($type, ['name' => 'Bella']);
        $this->makeResource($type, ['name' => 'Max']);
        $this->makeResource($type, ['name' => 'Sultan']);
        $slot = Timeslot::create([
            'resource_type_id' => $type->id,
            'name' => 'Začetniki',
            'weekdays' => [2, 4],
            'start_time' => '17:00',
            'end_time' => '18:00',
            'capacity' => 10,
        ]);

        return [$type, $slot, $horse];
    }

    private function book(ResourceType $type, Timeslot $slot, string $date, array $guest = []): Booking
    {
        $this->post(route('book.store', $type), $this->guest($guest + ['timeslot_id' => $slot->id, 'date' => $date]))
            ->assertSessionHasNoErrors();

        return Booking::latest('id')->first();
    }

    // ── Opomniki ──

    public function test_reminder_is_sent_once_within_the_window(): void
    {
        [$type, $slot] = $this->riding(['reminder_hours' => 24]);
        $booking = $this->book($type, $slot, '2026-11-26'); // četrtek 17:00

        // Ponedeljek 9:00 — do začetka več kot 24 ur
        $this->artisan('bookings:send-reminders')->assertSuccessful();
        Notification::assertSentOnDemandTimes(BookingReminder::class, 0);

        Carbon::setTestNow('2026-11-25 18:00');
        $this->artisan('bookings:send-reminders');
        $this->artisan('bookings:send-reminders');

        Notification::assertSentOnDemandTimes(BookingReminder::class, 1);
        $this->assertNotNull($booking->fresh()->reminder_sent_at);
    }

    public function test_no_reminder_for_pending_late_or_disabled_bookings(): void
    {
        [$pendingType, $pendingSlot] = $this->riding(['requires_approval' => true]);
        $this->book($pendingType, $pendingSlot, '2026-11-24');

        [$offType, $offSlot] = $this->riding(['reminder_hours' => null]);
        $this->book($offType, $offSlot, '2026-11-24', ['customer_email' => 'b@example.com']);

        // Rezervirano 8 ur pred začetkom pri 24-urnem opomniku
        [$type, $slot] = $this->riding();
        Carbon::setTestNow('2026-11-24 09:00');
        $late = $this->book($type, $slot, '2026-11-24', ['customer_email' => 'c@example.com']);

        $this->artisan('bookings:send-reminders');

        Notification::assertSentOnDemandTimes(BookingReminder::class, 0);
        $this->assertNotNull($late->fresh()->reminder_sent_at); // označeno, da se ne preverja znova
    }

    // ── Ročna sporočila ──

    public function test_message_to_customer_group_by_mail_and_portal(): void
    {
        $group = CustomerGroup::create(['name' => 'Člani']);
        $member = $this->customer();
        $member->customerGroups()->attach($group);
        $this->customer(); // ni v skupini
        $staff = $this->staff();

        $this->actingAs($staff)->postJson(route('admin.messages.preview'), ['mode' => 'customers', 'group_ids' => [$group->id]])
            ->assertOk()
            ->assertJson(['count' => 1, 'with_account' => 1]);

        $this->actingAs($staff)->post(route('admin.messages.store'), [
            'mode' => 'customers', 'group_ids' => [$group->id],
            'channels' => ['mail', 'portal'],
            'subject' => 'Novi termini', 'body' => "Pozdravljeni,\n\nod decembra imamo nove termine.",
        ])->assertRedirect()->assertSessionHas('success');

        $message = Message::sole();
        $this->assertSame(1, $message->recipients_count);
        $this->assertNotNull($message->completed_at);
        Notification::assertSentOnDemand(CustomerMessage::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $member->email);
        $this->assertSame(MessageRecipient::SENT, MessageRecipient::sole()->mail_status);

        // Nabiralnik: neprebrano, po ogledu prebrano
        $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('unreadMessages', 1));
        $this->actingAs($member)->get(route('inbox'))
            ->assertInertia(fn ($page) => $page->component('Inbox')->where('items.data.0.subject', 'Novi termini')->where('items.data.0.unread', true));
        $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('unreadMessages', 0));
    }

    public function test_message_to_customers_with_bookings_includes_guests_once(): void
    {
        [$type, $slot] = $this->riding();
        $customer = $this->customer();
        $this->actingAs($customer)->post(route('book.store', $type), ['timeslot_id' => $slot->id, 'date' => '2026-11-24']);
        $this->actingAs($customer)->post(route('book.store', $type), ['timeslot_id' => $slot->id, 'date' => '2026-11-26']);
        auth()->logout();
        $this->book($type, $slot, '2026-11-24', ['customer_email' => 'gost@example.com']);
        $this->book($type, $slot, '2026-12-01', ['customer_email' => 'kasneje@example.com']); // izven obdobja

        $audience = ['mode' => 'bookings', 'from' => '2026-11-23', 'to' => '2026-11-29', 'resource_type_id' => $type->id];

        $this->actingAs($this->staff())->postJson(route('admin.messages.preview'), $audience)
            ->assertJson(['count' => 2, 'with_account' => 1]);

        // Samo portal: gost nima nabiralnika
        $this->actingAs($this->staff())->post(route('admin.messages.store'), $audience + [
            'channels' => ['portal'], 'subject' => 'Sprememba', 'body' => 'Termin se začne ob 17:30.',
        ]);

        $this->assertSame([$customer->id], MessageRecipient::pluck('user_id')->all());
        Notification::assertSentOnDemandTimes(CustomerMessage::class, 0);
    }

    public function test_provider_messages_only_own_customers_and_needs_permission(): void
    {
        $maja = User::factory()->provider()->create();
        [$type, $slot, $horse] = $this->riding();
        $horse->update(['user_id' => $maja->id]);
        $other = $this->makeResource($type, ['name' => 'Luna']);
        $this->post(route('book.store', $type), $this->guest(['timeslot_id' => $slot->id, 'date' => '2026-11-24', 'resource_id' => $horse->id]));
        $this->post(route('book.store', $type), $this->guest(['customer_email' => 'drugi@example.com', 'timeslot_id' => $slot->id, 'date' => '2026-11-24', 'resource_id' => $other->id]));

        // Izvajalec privzeto nima pravice za sporočila
        $this->actingAs($maja)->get(route('admin.messages.create'))->assertForbidden();

        $maja->role->update(['permissions' => [...$maja->role->permissions, 'messages.send']]);
        $this->actingAs($maja->fresh())->postJson(route('admin.messages.preview'), ['mode' => 'bookings', 'from' => '2026-11-24', 'to' => '2026-11-24'])
            ->assertJson(['count' => 1, 'sample' => [['email' => 'ana@example.com']]]);
    }

    public function test_messages_pages_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.messages.store'), [
            'mode' => 'users', 'user_ids' => [$this->customer()->id], 'channels' => ['mail'], 'subject' => 'Test', 'body' => 'Vsebina',
        ]);

        $this->actingAs($admin)->get(route('admin.messages.index'))->assertOk()->assertInertia(fn ($page) => $page->where('messages.data.0.sent_count', 1));
        $this->actingAs($admin)->get(route('admin.messages.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.messages.show', Message::sole()))->assertOk();
        $this->actingAs($admin)->getJson(route('admin.messages.users', ['q' => 'admin']))->assertOk();
    }

    // ── Zaprtja ──

    public function test_closure_cancels_affected_bookings_and_notifies_customers(): void
    {
        [$type, $slot, $horse] = $this->riding();
        $this->book($type, $slot, '2026-11-24');
        $this->book($type, $slot, '2026-11-26', ['customer_email' => 'b@example.com']);
        $staff = $this->staff();
        $closure = ['resource_type_id' => $type->id, 'all_day' => true, 'starts_at' => '2026-11-24', 'ends_at' => '2026-11-24', 'reason' => 'Veterinar'];

        $this->actingAs($staff)->getJson(route('admin.closures.affected', $closure))
            ->assertOk()
            ->assertJson(['count' => 1, 'can_cancel' => true]);

        $this->actingAs($staff)->post(route('admin.closures.store'), $closure + ['cancel_bookings' => true])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('closures', 1);
        $this->assertSame(['cancelled', 'confirmed'], Booking::orderBy('starts_at')->pluck('status')->map->value->all());
        $this->assertSame('Veterinar', Booking::where('status', 'cancelled')->value('cancellation_reason'));
        Notification::assertSentOnDemand(BookingStatusChanged::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'ana@example.com');
    }

    public function test_closure_without_cancelling_keeps_bookings(): void
    {
        [$type, $slot] = $this->riding();
        $this->book($type, $slot, '2026-11-24');
        $closures = User::factory()->create(['role_id' => Role::create(['name' => 'Zaprtja', 'permissions' => ['closures.manage', 'bookings.view']])->id]);
        $closure = ['all_day' => true, 'starts_at' => '2026-11-24', 'ends_at' => '2026-11-24'];

        $this->actingAs($closures)->getJson(route('admin.closures.affected', $closure))->assertJson(['count' => 1, 'can_cancel' => false]);
        $this->actingAs($closures)->post(route('admin.closures.store'), $closure + ['cancel_bookings' => true])->assertForbidden();
        $this->actingAs($closures)->post(route('admin.closures.store'), $closure)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('closures', 1);
        $this->assertSame('confirmed', Booking::sole()->status->value);
    }
}
