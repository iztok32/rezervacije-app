<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Http\Presenters\BookingPresenter;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Osebje: današnje in čakajoče rezervacije ter kratka statistika.
     * Stranka: prihajajoče rezervacije in povezave za novo rezervacijo.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->hasPermission(Permission::BookingsView)) {
            $upcoming = Booking::with(['type', 'resource', 'service', 'timeslot'])
                ->where('user_id', $user->id)
                ->active()
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(5)
                ->get();

            return Inertia::render('Dashboard', [
                'upcoming' => $upcoming->map(fn ($b) => BookingPresenter::booking($b)),
            ]);
        }

        $visible = fn () => Booking::visibleTo($user);

        $today = $visible()->with(['type', 'resource', 'service', 'timeslot'])
            ->active()
            ->where('starts_at', '<', today()->addDay())
            ->where('ends_at', '>', today())
            ->orderBy('starts_at')
            ->get();

        $pending = $visible()->with(['type', 'resource', 'service', 'timeslot'])
            ->where('status', BookingStatus::Pending)
            ->orderBy('starts_at')
            ->limit(20)
            ->get();

        $stats = [
            'today' => $today->count(),
            'pending' => $visible()->where('status', BookingStatus::Pending)->count(),
            'next7' => $visible()->active()->whereBetween('starts_at', [now(), now()->addDays(7)])->count(),
            'created30' => $visible()->where('created_at', '>=', now()->subDays(30))->count(),
            'revenue30' => (float) $visible()->whereIn('status', ['confirmed', 'completed'])
                ->whereBetween('starts_at', [now()->subDays(30), now()])
                ->sum('total_price'),
        ];

        return Inertia::render('Admin/Dashboard', [
            'today' => $today->map(fn ($b) => BookingPresenter::booking($b, true)),
            'pending' => $pending->map(fn ($b) => BookingPresenter::booking($b, true)),
            'stats' => $stats,
        ]);
    }
}
