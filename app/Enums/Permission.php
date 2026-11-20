<?php

namespace App\Enums;

/**
 * Pravice osebja. Seznam je v kodi (ne v bazi), da vsaka funkcija vedno ve, katero pravico
 * preverja; administrator v aplikaciji samo izbere, katere pravice ima posamezna vloga.
 */
enum Permission: string
{
    case BookingsView = 'bookings.view';
    case BookingsManage = 'bookings.manage';
    case BookingsCreate = 'bookings.create';
    case OfferManage = 'offer.manage';
    case ClosuresManage = 'closures.manage';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case SettingsManage = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::BookingsView => __('View bookings and calendar'),
            self::BookingsManage => __('Manage bookings'),
            self::BookingsCreate => __('Book on behalf of customers'),
            self::OfferManage => __('Manage offer'),
            self::ClosuresManage => __('Manage closures'),
            self::UsersManage => __('Manage users and customer groups'),
            self::RolesManage => __('Manage roles and permissions'),
            self::SettingsManage => __('Manage settings'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BookingsView => __('Booking list, booking details and the occupancy calendar.'),
            self::BookingsManage => __('Confirm, reject, cancel and complete bookings, assign resources, internal notes.'),
            self::BookingsCreate => __('Book for a customer, also outside the booking window and in restricted offers.'),
            self::OfferManage => __('Resource types, resources, services, sessions and working hours.'),
            self::ClosuresManage => __('Holidays, vacations and other periods without bookings.'),
            self::UsersManage => __('Add and edit customers and staff, customer group membership.'),
            self::RolesManage => __('Create roles, assign permissions and roles to users.'),
            self::SettingsManage => __('Business details, booking rules and terms.'),
        };
    }

    /** @return array<int, array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'description' => $p->description(),
        ], self::cases());
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
