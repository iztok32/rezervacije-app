<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\WorkingHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Delovni čas tipa (privzeto) ali posameznega vira. Shrani se celoten urnik naenkrat.
 */
class WorkingHoursController extends Controller
{
    public function update(Request $request, ResourceType $resourceType): RedirectResponse
    {
        $validated = $request->validate([
            'resource_id' => 'nullable|integer',
            'hours' => 'present|array|max:70',
            'hours.*.weekday' => 'required|integer|between:1,7',
            'hours.*.start_time' => 'required|date_format:H:i',
            'hours.*.end_time' => 'required|date_format:H:i',
        ]);

        $resourceId = $validated['resource_id'] ?? null;
        if ($resourceId && ! Resource::where('resource_type_id', $resourceType->id)->whereKey($resourceId)->exists()) {
            abort(404);
        }

        $this->ensureValid($validated['hours']);

        DB::transaction(function () use ($resourceType, $resourceId, $validated) {
            WorkingHour::where('resource_type_id', $resourceType->id)
                ->where('resource_id', $resourceId)
                ->delete();

            foreach ($validated['hours'] as $h) {
                WorkingHour::create([
                    'resource_type_id' => $resourceType->id,
                    'resource_id' => $resourceId,
                    'weekday' => $h['weekday'],
                    'start_time' => $h['start_time'],
                    'end_time' => $h['end_time'],
                ]);
            }
        });

        return back()->with('success', __('Working hours saved.'));
    }

    /** Konec po začetku in brez prekrivanja intervalov istega dne. */
    private function ensureValid(array $hours): void
    {
        $byDay = [];
        foreach ($hours as $i => $h) {
            if ($h['end_time'] <= $h['start_time']) {
                throw ValidationException::withMessages(["hours.$i.end_time" => __('The end time must be after the start time.')]);
            }
            $byDay[$h['weekday']][] = [$h['start_time'], $h['end_time'], $i];
        }

        foreach ($byDay as $intervals) {
            usort($intervals, fn ($a, $b) => strcmp($a[0], $b[0]));
            for ($k = 1; $k < count($intervals); $k++) {
                if ($intervals[$k][0] < $intervals[$k - 1][1]) {
                    throw ValidationException::withMessages(["hours.{$intervals[$k][2]}.start_time" => __('Intervals on the same day must not overlap.')]);
                }
            }
        }
    }
}
