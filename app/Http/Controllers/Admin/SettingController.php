<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Nastavitve podjetja (ime, kontakt, obvestila, pogoji). */
class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => Setting::allWithDefaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_tagline' => 'nullable|string|max:255',
            'business_email' => 'nullable|email|max:255',
            'business_phone' => 'nullable|string|max:40',
            'business_address' => 'nullable|string|max:255',
            'business_website' => 'nullable|url|max:255',
            'business_currency' => 'required|string|size:3',
            'booking_notify_email' => 'nullable|email|max:255',
            'booking_terms' => 'nullable|string|max:20000',
            'booking_allow_registration' => 'boolean',
        ]);

        foreach ($validated as $field => $value) {
            // business_name → business.name
            $key = preg_replace('/_/', '.', $field, 1);
            Setting::set($key, is_bool($value) ? (int) $value : $value);
        }

        return back()->with('success', __('Settings saved.'));
    }
}
