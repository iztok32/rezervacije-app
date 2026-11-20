<?php

namespace App\Enums;

/**
 * Kdo izbere konkreten vir ob rezervaciji.
 */
enum ResourceSelection: string
{
    /** Stranka izbere vir (konja, frizerja, sobo). */
    case Customer = 'customer';

    /** Sistem dodeli prvi prost vir (stranka lahko izbere "kdorkoli"). */
    case Auto = 'auto';

    /** Vir dodeli osebje naknadno (npr. konja pred uro jahanja). */
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Customer => __('Customer chooses'),
            self::Auto => __('Assigned automatically'),
            self::Staff => __('Assigned by staff later'),
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
