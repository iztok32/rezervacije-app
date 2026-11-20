<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
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
    }
}
