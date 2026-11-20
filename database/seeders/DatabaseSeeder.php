<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use App\Support\Presets;
use Illuminate\Database\Seeder;

/**
 * Začetni podatki: administrator in (po želji) tipi virov iz predlog.
 *
 *   SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD  — prvi administrator (privzeto admin@example.com / password)
 *   SEED_PRESETS=hairdresser,court          — katere predloge naložiti (privzeto vse = demo)
 *   SEED_SAMPLES=false                      — brez vzorčnih virov, storitev in terminov
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Administrator',
                'role' => 'admin',
                'password' => env('SEED_ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
            ],
        );

        $presets = array_filter(explode(',', (string) env('SEED_PRESETS', implode(',', array_keys(Presets::all())))));
        $samples = filter_var(env('SEED_SAMPLES', true), FILTER_VALIDATE_BOOL);

        foreach ($presets as $preset) {
            Presets::apply(trim($preset), $samples);
        }

        // Demo: zaposleni in stranka za preizkus vlog
        if ($samples && app()->environment('local')) {
            User::firstOrCreate(['email' => 'staff@example.com'], [
                'name' => 'Zaposleni', 'role' => 'staff', 'password' => 'password', 'email_verified_at' => now(),
            ]);
            User::firstOrCreate(['email' => 'stranka@example.com'], [
                'name' => 'Stranka Demo', 'role' => 'customer', 'password' => 'password', 'email_verified_at' => now(),
                'phone' => '+386 40 123 456',
            ]);
            Setting::set('business.name', 'Demo rezervacije');
            Setting::set('business.tagline', 'Rezervirajte termin, storitev, vozilo ali nastanitev na enem mestu.');
        }
    }
}
