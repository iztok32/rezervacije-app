<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Koledar zasedenosti: teden (dnevi × rezervacije) ali časovnica po virih za izbrano obdobje.
 */
class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'type' => 'nullable|integer',
            'date' => 'nullable|date_format:Y-m-d',
            'view' => 'nullable|in:week,timeline',
        ]);

        $view = $validated['view'] ?? 'week';
        $date = Carbon::parse($validated['date'] ?? today());
        $from = $view === 'week' ? $date->copy()->startOfWeek() : $date->copy()->startOfDay();
        $to = $view === 'week' ? $from->copy()->addDays(7) : $from->copy()->addDays(14);
        $types = ResourceType::ordered()->get(['id', 'name', 'slug', 'color', 'booking_mode']);
        $type = isset($validated['type']) ? $types->firstWhere('id', (int) $validated['type']) : null;

        $bookings = Booking::with(['type', 'resource', 'service', 'timeslot'])
            ->active()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($type, fn ($q) => $q->where('resource_type_id', $type->id))
            ->orderBy('starts_at')
            ->get();

        $resources = Resource::query()
            ->when($type, fn ($q) => $q->where('resource_type_id', $type->id))
            ->active()
            ->with('type:id,name')
            ->orderBy('resource_type_id')->orderBy('display_order')->orderBy('name')
            ->get(['id', 'name', 'color', 'resource_type_id']);

        $closures = Closure::with(['resource:id,name', 'type:id,name'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get()
            ->map(fn (Closure $c) => [
                'id' => $c->id,
                'starts_at' => $c->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $c->ends_at->format('Y-m-d\TH:i'),
                'reason' => $c->reason,
                'resource_id' => $c->resource_id,
                'scope' => $c->resource?->name ?? $c->type?->name,
            ]);

        return Inertia::render('Admin/Calendar', [
            'view' => $view,
            'from' => $from->toDateString(),
            'to' => $to->copy()->subDay()->toDateString(),
            'date' => $date->toDateString(),
            'typeId' => $type?->id,
            'types' => $types,
            'bookings' => $bookings->map(fn ($b) => BookingPresenter::booking($b, true)),
            'resources' => $resources->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'color' => $r->color, 'type' => $r->type?->name]),
            'closures' => $closures,
        ]);
    }
}
