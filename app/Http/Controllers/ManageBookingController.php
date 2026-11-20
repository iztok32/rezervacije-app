<?php

namespace App\Http\Controllers;

use App\Http\Presenters\BookingPresenter;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ogled in odpoved rezervacije za stranko — prijavljenega lastnika ali gosta s povezavo z žetonom.
 */
class ManageBookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function show(Request $request, Booking $booking): Response
    {
        $token = $request->query('token');
        abort_unless($this->bookings->canView($booking, $request->user(), $token), 404);

        return Inertia::render('Bookings/Show', [
            'booking' => BookingPresenter::booking($booking),
            'token' => $token,
            'cancelBlocker' => $this->bookings->cancellationBlocker($booking, $request->user()),
            'cancellationHours' => $booking->type->cancellation_hours,
            'businessContact' => [
                'email' => Setting::get('business.email'),
                'phone' => Setting::get('business.phone'),
            ],
        ]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'nullable|string',
            'reason' => 'nullable|string|max:255',
        ]);

        $this->bookings->cancel($booking, $request->user(), $validated['token'] ?? null, $validated['reason'] ?? null);

        return back()->with('success', __('Your booking has been cancelled.'));
    }
}
