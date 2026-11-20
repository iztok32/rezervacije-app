<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\CustomerGroup;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Timeslot;
use Illuminate\Support\Carbon;

class ContentAndHistoryTest extends BookingTestCase
{
    // ── Vsebinske strani ──

    public function test_page_renders_safe_markdown(): void
    {
        $page = Page::create([
            'title' => 'O nas',
            'slug' => 'o-nas',
            'body' => "## Kje smo\n\n<script>alert(1)</script>\n\n[klik](javascript:alert(1)) in [karta](https://maps.example.com)",
        ]);

        $this->get(route('pages.show', 'o-nas'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Page')
                ->where('page.title', 'O nas')
                ->where('page.html', fn (string $html) => str_contains($html, '<h2>Kje smo</h2>')
                    && ! str_contains($html, '<script')
                    && ! str_contains($html, 'javascript:')
                    && str_contains($html, 'href="https://maps.example.com"')));

        $page->update(['is_published' => false]);
        $this->get(route('pages.show', 'o-nas'))->assertNotFound();
        $this->actingAs($this->admin())->get(route('pages.show', 'o-nas'))->assertOk();
    }

    public function test_admin_creates_page_with_unique_slug(): void
    {
        $admin = $this->admin();
        Page::create(['title' => 'Cenik', 'slug' => 'cenik']);

        $this->actingAs($admin)->post(route('admin.pages.store'), ['title' => 'Cenik', 'slug' => '', 'body' => 'Cene', 'is_published' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('pages', ['slug' => 'cenik-2']);
        $this->actingAs($this->staff())->get(route('admin.content.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.content.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.pages.edit', Page::where('slug', 'cenik-2')->sole()))->assertOk();
    }

    // ── Javni meni ──

    public function test_menu_respects_visibility_publication_and_access(): void
    {
        $about = Page::create(['title' => 'O nas', 'slug' => 'o-nas']);
        $draft = Page::create(['title' => 'Osnutek', 'slug' => 'osnutek', 'is_published' => false]);
        $open = $this->makeType('nightly', ['name' => 'Sobe']);
        $members = $this->makeType('timeslot', ['name' => 'Za člane']);
        $group = CustomerGroup::create(['name' => 'Člani']);
        $members->customerGroups()->attach($group);

        foreach ([
            ['kind' => 'page', 'page_id' => $about->id],
            ['kind' => 'page', 'page_id' => $draft->id],
            ['kind' => 'offer', 'resource_type_id' => $open->id, 'label' => 'Nastanitev'],
            ['kind' => 'offer', 'resource_type_id' => $members->id],
            ['kind' => 'link', 'url' => '/register', 'label' => 'Registracija', 'visibility' => 'guests'],
            ['kind' => 'link', 'url' => '/my-bookings', 'label' => 'Moje', 'visibility' => 'users'],
        ] as $i => $item) {
            MenuItem::create($item + ['location' => 'header', 'display_order' => $i]);
        }
        MenuItem::create(['location' => 'footer', 'kind' => 'link', 'url' => 'https://example.com', 'label' => 'Partner']);

        $labels = fn ($response) => collect($response->viewData('page')['props']['menu']['header'])->pluck('label')->all();

        $this->assertSame(['O nas', 'Nastanitev', 'Registracija'], $labels($this->get('/')));

        $member = $this->customer();
        $member->customerGroups()->attach($group);
        $this->assertSame(['O nas', 'Nastanitev', 'Za člane', 'Moje'], $labels($this->actingAs($member)->get('/')));

        $this->get('/')->assertInertia(fn ($page) => $page->where('menu.footer.0.external', true));
    }

    public function test_menu_links_must_be_safe(): void
    {
        $admin = $this->admin();
        $base = ['location' => 'header', 'kind' => 'link', 'label' => 'X', 'visibility' => 'all'];

        $this->actingAs($admin)->post(route('admin.menu-items.store'), $base + ['url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actingAs($admin)->post(route('admin.menu-items.store'), $base + ['url' => '//evil.example'])->assertSessionHasErrors('url');
        $this->actingAs($admin)->post(route('admin.menu-items.store'), $base + ['url' => 'https://example.com'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.menu-items.store'), $base + ['url' => '/p/o-nas'])->assertSessionHasNoErrors();

        $ids = MenuItem::orderBy('id')->pluck('id')->reverse()->values()->all();
        $this->actingAs($admin)->post(route('admin.menu-items.reorder'), ['ids' => $ids]);
        $this->assertSame('/p/o-nas', MenuItem::orderBy('display_order')->first()->url);
    }

    // ── Zgodovina rezervacije ──

    public function test_booking_history_records_who_did_what(): void
    {
        $type = $this->makeType('timeslot', ['requires_approval' => true, 'resource_selection' => 'staff', 'cancellation_hours' => null]);
        $bella = $this->makeResource($type, ['name' => 'Bella']);
        $slot = Timeslot::create(['resource_type_id' => $type->id, 'name' => 'Začetniki', 'weekdays' => [2], 'start_time' => '17:00', 'end_time' => '18:00', 'capacity' => 4]);

        $this->post(route('book.store', $type), $this->guest(['timeslot_id' => $slot->id, 'date' => '2026-11-24']));
        $booking = Booking::sole();
        $staff = $this->staff();

        $this->actingAs($staff)->post(route('admin.bookings.status', $booking), ['status' => 'confirmed']);
        $this->actingAs($staff)->patch(route('admin.bookings.update', $booking), ['resource_id' => $bella->id, 'internal_notes' => 'Prvič']);
        auth()->logout();

        Carbon::setTestNow('2026-11-23 18:00');
        $this->artisan('bookings:send-reminders');

        $this->post(route('bookings.cancel', ['booking' => $booking->reference, 'token' => $booking->access_token]), ['reason' => 'Bolezen']);

        $this->actingAs($this->admin())->get(route('admin.bookings.show', $booking))
            ->assertInertia(fn ($page) => $page
                ->has('history', 6)
                ->where('history.0.event', 'created')
                ->where('history.0.actor', 'Ana Novak ('.__('customer').')')
                ->where('history.1.event', 'status_changed')
                ->where('history.1.actor', $staff->name)
                ->where('history.1.text', __('Status: :from → :to', ['from' => __('Pending'), 'to' => __('Confirmed')]))
                ->where('history.2.text', __('Resource: :from → :to', ['from' => '—', 'to' => 'Bella']))
                ->where('history.3.event', 'notes_updated')
                ->where('history.4.actor', __('System'))
                ->where('history.5.text', __('Status: :from → :to', ['from' => __('Confirmed'), 'to' => __('Cancelled')]))
                ->where('history.5.note', 'Bolezen'));
    }
}
