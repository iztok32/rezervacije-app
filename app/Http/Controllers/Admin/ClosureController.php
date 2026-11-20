<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\User;
use App\Services\Booking\BookingNotifier;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zaprtja in odsotnosti (prazniki, dopust, servis …). Ob dodajanju lahko prizadete
 * rezervacije odpove in o tem obvesti stranke.
 */
class ClosureController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(): Response
    {
        $closures = Closure::with(['type:id,name', 'resource:id,name'])
            ->where('ends_at', '>=', now()->subDays(30))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Closure $c) => [
                'id' => $c->id,
                'type' => $c->type?->name,
                'resource' => $c->resource?->name,
                'starts_at' => $c->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $c->ends_at->format('Y-m-d\TH:i'),
                'reason' => $c->reason,
            ]);

        return Inertia::render('Admin/Closures/Index', [
            'closures' => $closures,
            'types' => ResourceType::ordered()->get(['id', 'name']),
            'resources' => Resource::orderBy('name')->get(['id', 'name', 'resource_type_id']),
        ]);
    }

    /** Aktivne rezervacije, ki jih zadene zaprtje (predogled v obrazcu). */
    public function affected(Request $request): JsonResponse
    {
        $closure = $this->closure($request);
        $bookings = $this->affectedBookings($closure, $request->user());

        return response()->json([
            'count' => $bookings->count(),
            'can_cancel' => $request->user()->hasPermission(Permission::BookingsManage),
            'bookings' => $bookings->take(20)->map(fn (Booking $b) => [
                'reference' => $b->reference,
                'title' => $b->title(),
                'when' => BookingNotifier::when($b),
                'customer' => $b->customer_name,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $closure = $this->closure($request);
        $cancel = $request->boolean('cancel_bookings');
        $actor = $request->user();

        if ($cancel && ! $actor->hasPermission(Permission::BookingsManage)) {
            abort(403);
        }

        $closure->save();

        if (! $cancel) {
            return back()->with('success', __('Closure added.'));
        }

        // Odpoved prizadetih rezervacij; stranke dobijo obvestilo o odpovedi z razlogom
        $reason = $closure->reason ?: __('Closed');
        $cancelled = 0;
        foreach ($this->affectedBookings($closure, $actor) as $booking) {
            $this->bookings->changeStatus($booking, BookingStatus::Cancelled, $actor, $reason);
            $cancelled++;
        }

        return back()->with('success', trans_choice(
            'Closure added, :count booking cancelled and the customer notified.|Closure added, :count bookings cancelled and the customers notified.',
            $cancelled,
            ['count' => $cancelled],
        ));
    }

    public function destroy(Closure $closure): RedirectResponse
    {
        $closure->delete();

        return back()->with('success', __('Deleted.'));
    }

    /** Zaprtje iz obrazca (še ne shranjeno). */
    private function closure(Request $request): Closure
    {
        $allDay = $request->boolean('all_day');
        $validated = $request->validate([
            'resource_type_id' => 'nullable|integer|exists:resource_types,id',
            'resource_id' => 'nullable|integer|exists:resources,id',
            'starts_at' => 'required|date',
            // Celodnevno zaprtje je lahko en sam dan (prvi = zadnji dan)
            'ends_at' => ['required', 'date', $allDay ? 'after_or_equal:starts_at' : 'after:starts_at'],
            'all_day' => 'boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        // Zaprtje za vir vedno nosi tudi tip vira
        if (! empty($validated['resource_id'])) {
            $validated['resource_type_id'] = Resource::find($validated['resource_id'])->resource_type_id;
        }

        // Celodnevno: od začetka prvega do konca zadnjega dne
        if ($allDay) {
            $validated['starts_at'] = Carbon::parse($validated['starts_at'])->startOfDay();
            $validated['ends_at'] = Carbon::parse($validated['ends_at'])->startOfDay()->addDay();
        }

        return new Closure(collect($validated)->except('all_day')->all());
    }

    /** @return Collection<int, Booking> */
    private function affectedBookings(Closure $closure, User $actor): Collection
    {
        return Booking::visibleTo($actor)
            ->with(['type', 'resource', 'service', 'timeslot'])
            ->active()
            ->where('starts_at', '<', $closure->ends_at)
            ->where('ends_at', '>', $closure->starts_at)
            ->when($closure->resource_id, fn ($q, $id) => $q->where('resource_id', $id))
            ->when(! $closure->resource_id && $closure->resource_type_id, fn ($q) => $q->where('resource_type_id', $closure->resource_type_id))
            ->orderBy('starts_at')
            ->get();
    }
}
