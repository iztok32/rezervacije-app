<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Services\Booking\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pregled in upravljanje rezervacij (osebje). */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => 'nullable|integer',
            'status' => ['nullable', Rule::in(array_merge(['active'], array_column(BookingStatus::cases(), 'value')))],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'q' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        $query = Booking::visibleTo($user)
            ->with(['type', 'resource', 'service', 'timeslot', 'user:id,name,email'])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('resource_type_id', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $status === 'active' ? $q->active() : $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('ends_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('starts_at', '<', Carbon::parse($to)->addDay()))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw('LOWER(customer_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(customer_email) LIKE ?', [$like])
                    ->orWhere('customer_phone', 'like', '%'.$term.'%')
                    ->orWhere('reference', strtoupper($term)));
            });

        // Brez filtra datuma najprej prihajajoče, sicer kronološko
        $upcomingFirst = empty($filters['from']) && empty($filters['to']);
        $paginator = ($upcomingFirst
                ? $query->where('ends_at', '>=', today())->orderBy('starts_at')
                : $query->orderBy('starts_at'))
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => [
                'data' => collect($paginator->items())->map(fn ($b) => BookingPresenter::booking($b, true)),
                'links' => $paginator->linkCollection(),
                'total' => $paginator->total(),
            ],
            'filters' => $filters + ['upcoming_only' => $upcomingFirst],
            'types' => ResourceType::withTrashed()->ordered()
                ->when($user->allowedResourceTypeIds(), fn ($q, $ids) => $q->whereIn('id', $ids))
                ->get(['id', 'name', 'slug']),
            'statuses' => BookingStatus::options(),
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        abort_unless($booking->isVisibleTo($request->user()), 403);

        $resources = Resource::where('resource_type_id', $booking->resource_type_id)
            ->active()
            ->orderBy('display_order')->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Bookings/Show', [
            'booking' => BookingPresenter::booking($booking, true),
            'manageUrl' => $booking->manageUrl(),
            'transitions' => collect($this->bookings->allowedTransitions($booking))
                ->map(fn ($s) => ['value' => $s, 'label' => BookingStatus::from($s)->label()])
                ->values(),
            'resources' => $resources,
            'canManage' => $booking->isManageableBy($request->user()),
        ]);
    }

    /** Dodelitev vira in interne opombe. */
    public function update(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->isManageableBy($request->user()), 403);

        $validated = $request->validate([
            'resource_id' => 'sometimes|nullable|integer',
            'internal_notes' => 'sometimes|nullable|string|max:1000',
        ]);

        if (array_key_exists('resource_id', $validated) && $validated['resource_id'] !== $booking->resource_id) {
            $this->bookings->assignResource($booking, $validated['resource_id'], $request->user());
        }

        if (array_key_exists('internal_notes', $validated)) {
            $booking->update(['internal_notes' => $validated['internal_notes']]);
        }

        return back()->with('success', __('Booking updated.'));
    }

    public function status(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(BookingStatus::class)],
            'reason' => 'nullable|string|max:255',
        ]);

        $this->bookings->changeStatus($booking, BookingStatus::from($validated['status']), $request->user(), $validated['reason'] ?? null);

        return back()->with('success', __('Status changed to: :status.', ['status' => $booking->status->label()]));
    }
}
