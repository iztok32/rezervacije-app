<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Timeslot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Ponavljajoči se termini s kapaciteto (način "timeslot"). */
class TimeslotController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:5000',
        'weekdays' => 'required|array|min:1',
        'weekdays.*' => 'integer|between:1,7',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'valid_from' => 'nullable|date_format:Y-m-d',
        'valid_to' => 'nullable|date_format:Y-m-d|after_or_equal:valid_from',
        'capacity' => 'nullable|integer|min:1|max:10000',
        'price' => 'nullable|numeric|min:0|max:99999999',
        'is_active' => 'boolean',
        'resource_ids' => 'nullable|array',
        'resource_ids.*' => 'integer',
        'staff_ids' => 'nullable|array',
        'staff_ids.*' => 'integer',
    ];

    public function store(Request $request, ResourceType $resourceType): RedirectResponse
    {
        $validated = $request->validate(self::RULES);

        $timeslot = Timeslot::create($this->attributes($validated) + [
            'resource_type_id' => $resourceType->id,
            'display_order' => (int) Timeslot::where('resource_type_id', $resourceType->id)->max('display_order') + 1,
        ]);
        $this->syncRelations($timeslot, $validated);

        return back()->with('success', __('Saved.'));
    }

    public function update(Request $request, Timeslot $timeslot): RedirectResponse
    {
        $validated = $request->validate(self::RULES);

        // Sprememba ure pri obstoječih prihodnjih prijavah bi prijave "izgubila" iz koledarja
        $timeChanged = substr($timeslot->start_time, 0, 5) !== $validated['start_time'];
        if ($timeChanged && Booking::where('timeslot_id', $timeslot->id)->active()->where('starts_at', '>=', now())->exists()) {
            return back()->withErrors(['start_time' => __('The start time cannot be changed while there are upcoming bookings. Create a new session instead.')]);
        }

        $timeslot->update($this->attributes($validated));
        $this->syncRelations($timeslot, $validated);

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Timeslot $timeslot): RedirectResponse
    {
        if (Booking::where('timeslot_id', $timeslot->id)->active()->where('starts_at', '>=', now())->exists()) {
            return back()->with('error', __('This session has upcoming bookings. Deactivate it or set an end date instead.'));
        }

        $timeslot->delete();

        return back()->with('success', __('Deleted.'));
    }

    private function attributes(array $validated): array
    {
        $data = collect($validated)->except(['resource_ids', 'staff_ids'])->all();
        $data['weekdays'] = array_values(array_unique(array_map('intval', $validated['weekdays'])));
        sort($data['weekdays']);

        return $data;
    }

    private function syncRelations(Timeslot $timeslot, array $validated): void
    {
        $timeslot->resources()->sync(
            Resource::where('resource_type_id', $timeslot->resource_type_id)->whereIn('id', $validated['resource_ids'] ?? [])->pluck('id')
        );
        $timeslot->staff()->sync(
            User::whereIn('id', $validated['staff_ids'] ?? [])->pluck('id')
        );
    }
}
