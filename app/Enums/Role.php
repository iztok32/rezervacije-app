<?php

namespace App\Enums;

enum Role: string
{
    /** Vse: katalog, nastavitve, uporabniki, rezervacije. */
    case Admin = 'admin';

    /** Osebje: pregled in upravljanje rezervacij, koledar. */
    case Staff = 'staff';

    /** Stranka: svoje rezervacije. */
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Staff => __('Staff'),
            self::Customer => __('Customer'),
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
