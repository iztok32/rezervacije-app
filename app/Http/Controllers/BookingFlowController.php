<?php

namespace App\Http\Controllers;

use App\Enums\BookingMode;
use App\Http\Presenters\BookingPresenter;
use App\Models\ResourceType;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rezervacijski tok za tip vira: stran z izbirnikom, razpoložljivost (JSON) in oddaja.
 * Isti tok uporablja osebje za vnos rezervacije za stranko.
 */
class BookingFlowController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private Availability $availability,
    ) {}

    public function show(Request $request, ResourceType $resourceType): Response
    {
        $user = $request->user();
        abort_unless($resourceType->is_active || $user?->isStaff(), 404);

        $resources = $this->availability->eligibleResources($resourceType)
            ->map(fn ($r) => BookingPresenter::resource($r))
            ->values();

        $services = $resourceType->booking_mode === BookingMode::Service
            ? Service::with('resources:id')
                ->where('resource_type_id', $resourceType->id)
                ->active()
                ->orderBy('display_order')->orderBy('name')
                ->get()
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'description' => $s->description,
                    'duration_minutes' => $s->duration_minutes,
                    'price' => $s->price !== null ? (float) $s->price : null,
                    'resource_ids' => $s->resources->pluck('id')->all(),
                ])
            : [];

        // Po prijavi ali registraciji nazaj na rezervacijo
        if (! $user && ! $resourceType->allow_guests) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('Book/Show', [
            'type' => BookingPresenter::type($resourceType),
            'resources' => $resources,
            'services' => $services,
            'today' => today()->toDateString(),
            'lastBookableDate' => $this->availability->lastBookableDate($resourceType)->toDateString(),
            'requiresLogin' => ! $user && ! $resourceType->allow_guests,
            'terms' => Setting::get('booking.terms'),
            'isStaff' => (bool) $user?->isStaff(),
        ]);
    }

    public function availability(Request $request, ResourceType $resourceType): JsonResponse
    {
        abort_unless($resourceType->is_active || $request->user()?->isStaff(), 404);

        $handler = $this->bookings->handler($resourceType);
        $query = $request->validate($handler->availabilityRules());

        return response()->json($handler->availability($resourceType, $query, $request->user()));
    }

    public function store(Request $request, ResourceType $resourceType): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate($this->bookings->rules($resourceType, $user));

        $booking = $this->bookings->create($resourceType, $validated, $user);

        // Osebje se vrne na podrobnosti v administraciji, stranka na potrditveno stran
        if ($user?->isStaff()) {
            return redirect()->route('admin.bookings.show', $booking)
                ->with('success', __('Booking :reference created.', ['reference' => $booking->reference]));
        }

        return redirect()->to($booking->manageUrl())->with('success', $booking->status->value === 'pending'
            ? __('Thank you! Your booking has been received and is awaiting confirmation.')
            : __('Thank you! Your booking is confirmed.'));
    }
}
