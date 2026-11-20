<?php

use App\Support\Presets;
use Illuminate\Support\Facades\Artisan;

/*
 * Doda tip vira iz predloge, npr. ob postavitvi instance za novo stranko:
 *   php artisan preset:add hairdresser --samples
 *   php artisan preset:add glamping --name="Hiške ob jezeru"
 */
Artisan::command('preset:add {preset?} {--name=} {--samples}', function (?string $preset = null) {
    $all = Presets::all();

    if (! $preset) {
        $this->table(['Predloga', 'Opis', 'Način'], collect($all)->map(fn ($p, $k) => [$k, $p['label'], $p['type']['booking_mode']])->values());

        return 0;
    }

    if (! isset($all[$preset])) {
        $this->error("Neznana predloga [$preset]. Možne: ".implode(', ', array_keys($all)));

        return 1;
    }

    $type = Presets::apply($preset, (bool) $this->option('samples'), array_filter(['name' => $this->option('name')]));
    $this->info("Dodan tip \"{$type->name}\" (/book/{$type->slug}).");

    return 0;
})->purpose('Doda tip vira iz predloge (brez argumenta izpiše predloge)');
