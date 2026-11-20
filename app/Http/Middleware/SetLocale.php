<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jezik: izbira uporabnika (profil) → izbira v seji → jezik brskalnika → privzeti jezik.
 * Neaktivni uporabniki so ob naslednji zahtevi odjavljeni.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('Your account has been deactivated.')]);
        }

        $available = config('app.available_locales');
        $locale = $user?->locale
            ?? $request->session()->get('locale')
            ?? $request->getPreferredLanguage($available);

        if ($locale && in_array($locale, $available, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
