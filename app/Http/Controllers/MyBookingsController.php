<?php

namespace App\Http\Controllers;

use App\Http\Presenters\BookingPresenter;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyBookingsController extends Controller
{
    /** Rezervacije prijavljenega uporabnika: prihajajoče in pretekle. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $base = Booking::with(['type', 'resource', 'service', 'timeslot'])->where('user_id', $user->id);

        $upcoming = (clone $base)->where('ends_at', '>=', now())->orderBy('starts_at')->limit(100)->get();
        $past = (clone $base)->where('ends_at', '<', now())->orderByDesc('starts_at')->limit(50)->get();

        return Inertia::render('Bookings/Mine', [
            'upcoming' => $upcoming->map(fn ($b) => BookingPresenter::booking($b)),
            'past' => $past->map(fn ($b) => BookingPresenter::booking($b)),
        ]);
    }
}
