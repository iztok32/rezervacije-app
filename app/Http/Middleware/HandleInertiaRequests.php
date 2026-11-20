<?php

namespace App\Http\Middleware;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\ResourceType;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role?->name,
                    'is_staff' => $user->isStaff(),
                    'is_admin' => $user->isAdmin(),
                    'permissions' => $user->permissionList(),
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
            ],
            'business' => fn () => [
                'name' => Setting::businessName(),
                'tagline' => Setting::get('business.tagline'),
                'email' => Setting::get('business.email'),
                'phone' => Setting::get('business.phone'),
                'address' => Setting::get('business.address'),
                'website' => Setting::get('business.website'),
                'currency' => Setting::get('business.currency', 'EUR'),
                'allow_registration' => (bool) Setting::get('booking.allow_registration', '1'),
            ],
            // Tipi virov za navigacijo (rezerviraj …)
            'bookableTypes' => fn () => ResourceType::active()->ordered()->openTo($user)
                ->get(['id', 'name', 'slug', 'icon', 'color'])
                ->toArray(),
            'pendingBookings' => fn () => $user?->hasPermission(Permission::BookingsView)
                ? Booking::visibleTo($user)->where('status', BookingStatus::Pending)->count()
                : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => $locale,
            'availableLocales' => config('app.available_locales'),
            'translations' => fn () => $this->translations($locale),
        ];
    }

    private function translations(string $locale): array
    {
        $path = lang_path($locale.'.json');

        return is_file($path) ? (json_decode(file_get_contents($path), true) ?? []) : [];
    }
}
