<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Nastavitve podjetja (ključ → vrednost). Privzete vrednosti so v DEFAULTS.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'business.name' => null,      // null = config('app.name')
        'business.tagline' => null,
        'business.email' => null,
        'business.phone' => null,
        'business.address' => null,
        'business.website' => null,
        'business.currency' => 'EUR',
        'booking.notify_email' => null,      // e-naslov za obvestila o novih rezervacijah
        'booking.terms' => null,      // pogoji, ki jih stranka potrdi ob rezervaciji
        'booking.allow_registration' => '1',
    ];

    private const CACHE_KEY = 'settings.all';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value === null || $value === '' ? null : (string) $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** Vse znane nastavitve s privzetimi vrednostmi. */
    public static function allWithDefaults(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public static function businessName(): string
    {
        return self::get('business.name') ?: config('app.name');
    }
}
