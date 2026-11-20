<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Presets;
use Illuminate\Database\Seeder;

/**
 * Začetni podatki: administrator in (po želji) tipi virov iz predlog.
 * Vloge (Administrator, Recepcija, Izvajalec) ustvari že migracija.
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
                'role_id' => Role::admin()?->id,
                'password' => env('SEED_ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
            ],
        );

        $presets = array_filter(explode(',', (string) env('SEED_PRESETS', implode(',', array_keys(Presets::all())))));
        $samples = filter_var(env('SEED_SAMPLES', true), FILTER_VALIDATE_BOOL);

        foreach ($presets as $preset) {
            Presets::apply(trim($preset), $samples);
        }

        if ($samples && app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
