<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Timeslot;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo uporabniki za preizkus vlog in skupin strank (geslo: password). Lahko se požene
 * tudi naknadno: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $demo = fn (string $email, array $attributes) => User::firstOrCreate(['email' => $email], $attributes + [
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $demo('staff@example.com', ['name' => 'Recepcija Demo', 'role_id' => Role::bySlug('reception')?->id]);

        // Izvajalka vidi samo rezervacije svojega vira (frizerka Maja)
        $maja = $demo('maja@example.com', ['name' => 'Maja', 'role_id' => Role::bySlug('provider')?->id]);
        Resource::where('name', 'Maja')->whereNull('user_id')->update(['user_id' => $maja->id]);

        $customer = $demo('stranka@example.com', ['name' => 'Stranka Demo', 'phone' => '+386 40 123 456']);

        // Termin za napredne jahače je viden samo članom skupine
        $advanced = Timeslot::where('name', __('Advanced'))->first();
        if ($advanced) {
            $group = CustomerGroup::firstOrCreate(['name' => __('Advanced riders')], [
                'description' => __('Can book sessions for advanced riders.'),
                'color' => '#b45309',
            ]);
            $advanced->customerGroups()->syncWithoutDetaching([$group->id]);
            $customer->customerGroups()->syncWithoutDetaching([$group->id]);
        }

        Setting::set('business.name', 'Demo rezervacije');
        Setting::set('business.tagline', 'Rezervirajte termin, storitev, vozilo ali nastanitev na enem mestu.');

        $this->content();
    }

    /** Vsebinski strani in javni meni. */
    private function content(): void
    {
        if (MenuItem::exists()) {
            return;
        }

        $about = Page::firstOrCreate(['slug' => 'o-nas'], [
            'title' => 'O nas',
            'body' => "Smo družinsko podjetje z dolgoletno tradicijo.\n\n## Kje smo\n\nNajdete nas v središču vasi, parkirišče je ob stavbi.\n\n## Delovni čas\n\n- ponedeljek–petek: 8.00–18.00\n- sobota: 8.00–12.00",
        ]);
        $terms = Page::firstOrCreate(['slug' => 'splosni-pogoji'], [
            'title' => 'Splošni pogoji',
            'body' => "## Rezervacije\n\nRezervacija je veljavna, ko jo potrdimo po e-pošti.\n\n## Odpoved\n\nRezervacijo lahko brezplačno odpoveste do roka, navedenega pri ponudbi, prek povezave v potrditvenem sporočilu.",
        ]);

        MenuItem::create(['location' => 'header', 'kind' => 'page', 'page_id' => $about->id, 'display_order' => 0]);
        MenuItem::create(['location' => 'footer', 'kind' => 'page', 'page_id' => $about->id, 'display_order' => 0]);
        MenuItem::create(['location' => 'footer', 'kind' => 'page', 'page_id' => $terms->id, 'display_order' => 1]);
    }
}
