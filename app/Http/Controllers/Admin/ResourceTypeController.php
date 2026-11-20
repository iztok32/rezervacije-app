<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingMode;
use App\Enums\ResourceSelection;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Presenters\BookingPresenter;
use App\Models\Booking;
use App\Models\Closure;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use App\Models\Timeslot;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\Presets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Tipi virov (administrator): seznam, nastavitve in pregled vsebine tipa. */
class ResourceTypeController extends Controller
{
    public function index(): Response
    {
        $types = ResourceType::ordered()
            ->withCount([
                'resources',
                'bookings as upcoming_count' => fn ($q) => $q->active()->where('starts_at', '>=', now()),
            ])
            ->get()
            ->map(fn (ResourceType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'icon' => $t->icon,
                'color' => $t->color,
                'booking_mode' => $t->booking_mode->value,
                'mode_label' => $t->booking_mode->label(),
                'is_active' => $t->is_active,
                'allow_guests' => $t->allow_guests,
                'requires_approval' => $t->requires_approval,
                'resources_count' => $t->resources_count,
                'upcoming_count' => $t->upcoming_count,
            ]);

        return Inertia::render('Admin/ResourceTypes/Index', ['types' => $types]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ResourceTypes/Create', [
            'presets' => Presets::options(),
            'modes' => BookingMode::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preset' => ['nullable', Rule::in(array_keys(Presets::all()))],
            'with_samples' => 'boolean',
            'name' => 'required|string|max:255',
            'booking_mode' => ['required_without:preset', 'nullable', Rule::enum(BookingMode::class)],
        ]);

        if (! empty($validated['preset'])) {
            $type = Presets::apply($validated['preset'], (bool) ($validated['with_samples'] ?? false), ['name' => $validated['name']]);
        } else {
            $type = ResourceType::create([
                'name' => $validated['name'],
                'slug' => Presets::uniqueSlug($validated['name']),
                'booking_mode' => $validated['booking_mode'],
                'is_active' => false,
                'display_order' => (int) ResourceType::max('display_order') + 1,
            ]);
        }

        return redirect()->route('admin.resource-types.edit', $type)
            ->with('success', __('Created. Review the settings and add resources.'));
    }

    public function edit(ResourceType $resourceType): Response
    {
        $type = $resourceType;

        return Inertia::render('Admin/ResourceTypes/Edit', [
            'type' => BookingPresenter::type($type) + [
                'check_in_time_raw' => $type->check_in_time ? substr($type->check_in_time, 0, 5) : null,
                'check_out_time_raw' => $type->check_out_time ? substr($type->check_out_time, 0, 5) : null,
                'resource_label_raw' => $type->resource_label,
            ],
            'resources' => Resource::where('resource_type_id', $type->id)
                ->orderBy('display_order')->orderBy('name')
                ->get()
                ->map(fn (Resource $r) => BookingPresenter::resource($r) + [
                    'is_active' => $r->is_active,
                    'user_id' => $r->user_id,
                    'display_order' => $r->display_order,
                ]),
            'services' => Service::with('resources:id')->where('resource_type_id', $type->id)
                ->orderBy('display_order')->orderBy('name')
                ->get()
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'description' => $s->description,
                    'duration_minutes' => $s->duration_minutes,
                    'buffer_minutes' => $s->buffer_minutes,
                    'price' => $s->price !== null ? (float) $s->price : null,
                    'is_active' => $s->is_active,
                    'resource_ids' => $s->resources->pluck('id')->all(),
                ]),
            'timeslots' => Timeslot::with(['resources:id', 'staff:id'])->where('resource_type_id', $type->id)
                ->orderBy('display_order')->orderBy('start_time')
                ->get()
                ->map(fn (Timeslot $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                    'weekdays' => array_map('intval', $t->weekdays ?? []),
                    'start_time' => substr($t->start_time, 0, 5),
                    'end_time' => substr($t->end_time, 0, 5),
                    'valid_from' => $t->valid_from?->toDateString(),
                    'valid_to' => $t->valid_to?->toDateString(),
                    'capacity' => $t->capacity,
                    'price' => $t->price !== null ? (float) $t->price : null,
                    'is_active' => $t->is_active,
                    'resource_ids' => $t->resources->pluck('id')->all(),
                    'staff_ids' => $t->staff->pluck('id')->all(),
                ]),
            'workingHours' => WorkingHour::where('resource_type_id', $type->id)
                ->orderBy('weekday')->orderBy('start_time')
                ->get()
                ->map(fn (WorkingHour $h) => [
                    'resource_id' => $h->resource_id,
                    'weekday' => $h->weekday,
                    'start_time' => substr($h->start_time, 0, 5),
                    'end_time' => substr($h->end_time, 0, 5),
                ]),
            'closures' => Closure::with('resource:id,name')
                ->where('resource_type_id', $type->id)
                ->where('ends_at', '>=', now()->subDays(30))
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Closure $c) => [
                    'id' => $c->id,
                    'resource' => $c->resource?->name,
                    'starts_at' => $c->starts_at->format('Y-m-d\TH:i'),
                    'ends_at' => $c->ends_at->format('Y-m-d\TH:i'),
                    'reason' => $c->reason,
                ]),
            'staff' => User::whereIn('role', [Role::Admin, Role::Staff])->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
            'modes' => BookingMode::options(),
            'selections' => ResourceSelection::options(),
        ]);
    }

    public function update(Request $request, ResourceType $resourceType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('resource_types', 'slug')->ignore($resourceType->id)],
            'description' => 'nullable|string|max:5000',
            'icon' => 'nullable|string|max:40',
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'booking_mode' => ['required', Rule::enum(BookingMode::class)],
            'resource_label' => 'nullable|string|max:100',
            'resource_selection' => ['required', Rule::enum(ResourceSelection::class)],
            'allow_guests' => 'boolean',
            'requires_approval' => 'boolean',
            'min_advance_minutes' => 'required|integer|min:0|max:525600',
            'max_advance_days' => 'required|integer|min:0|max:1095',
            'cancellation_hours' => 'nullable|integer|min:0|max:8760',
            'slot_interval_minutes' => 'required|integer|min:5|max:1440',
            'min_duration_minutes' => 'nullable|integer|min:5|max:1440',
            'max_duration_minutes' => 'nullable|integer|min:5|max:1440|gte:min_duration_minutes',
            'nightly_unit' => 'required|in:night,day',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
            'min_units' => 'required|integer|min:1|max:365',
            'max_units' => 'nullable|integer|min:1|max:365|gte:min_units',
            'max_quantity_per_booking' => 'required|integer|min:1|max:100',
            'is_active' => 'boolean',
        ]);

        // Zamenjava načina ob obstoječih prihodnjih rezervacijah bi jih pustila brez pravil
        if ($validated['booking_mode'] !== $resourceType->booking_mode->value
            && Booking::where('resource_type_id', $resourceType->id)->active()->where('ends_at', '>=', now())->exists()) {
            return back()->withErrors(['booking_mode' => __('The booking mode cannot be changed while there are upcoming bookings.')]);
        }

        $validated['icon'] = $validated['icon'] ?: 'calendar';
        $resourceType->update($validated);

        return redirect()->route('admin.resource-types.edit', $resourceType)->with('success', __('Saved.'));
    }

    public function destroy(ResourceType $resourceType): RedirectResponse
    {
        if (Booking::where('resource_type_id', $resourceType->id)->active()->where('ends_at', '>=', now())->exists()) {
            return back()->with('error', __('This type has upcoming bookings. Deactivate it instead, or cancel the bookings first.'));
        }

        // Sprosti slug za morebiten nov tip z istim imenom
        $resourceType->update(['slug' => $resourceType->slug.'-deleted-'.$resourceType->id, 'is_active' => false]);
        $resourceType->delete();

        return redirect()->route('admin.resource-types.index')->with('success', __('Deleted.'));
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        foreach ($validated['ids'] as $index => $id) {
            ResourceType::whereKey($id)->update(['display_order' => $index]);
        }

        return back();
    }
}
