<?php

use App\Services\Notifications\ReminderSender;
use App\Support\Presets;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

/*
 * Opomniki strankam pred začetkom rezervacije (čas opomnika je nastavitev tipa vira).
 * V produkciji teče razporejevalnik (schedule:work v supervisord), lokalno: php artisan schedule:work
 */
Artisan::command('bookings:send-reminders', function (ReminderSender $reminders) {
    $this->info('Poslanih opomnikov: '.$reminders->send());
})->purpose('Pošlje opomnike pred začetkom rezervacij');

Schedule::command('bookings:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
