<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case NoShow = 'no_show';

    /** Statusi, pri katerih rezervacija zaseda vir. */
    public const ACTIVE = ['pending', 'confirmed'];

    public function isActive(): bool
    {
        return in_array($this->value, self::ACTIVE, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::Cancelled => __('Cancelled'),
            self::Rejected => __('Rejected'),
            self::Completed => __('Completed'),
            self::NoShow => __('No-show'),
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
