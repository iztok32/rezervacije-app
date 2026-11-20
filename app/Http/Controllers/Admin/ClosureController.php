<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Closure;
use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Zaprtja in odsotnosti (prazniki, dopust, servis …). */
class ClosureController extends Controller
{
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'resource_type_id' => 'nullable|integer|exists:resource_types,id',
            'resource_id' => 'nullable|integer|exists:resources,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'all_day' => 'boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        // Zaprtje za vir vedno nosi tudi tip vira
        if (! empty($validated['resource_id'])) {
            $validated['resource_type_id'] = Resource::find($validated['resource_id'])->resource_type_id;
        }

        // Celodnevno: od začetka prvega do konca zadnjega dne
        if ($validated['all_day'] ?? false) {
            $validated['starts_at'] = Carbon::parse($validated['starts_at'])->startOfDay();
            $validated['ends_at'] = Carbon::parse($validated['ends_at'])->startOfDay()->addDay();
        }

        Closure::create(collect($validated)->except('all_day')->all());

        return back()->with('success', __('Closure added.'));
    }

    public function destroy(Closure $closure): RedirectResponse
    {
        $closure->delete();

        return back()->with('success', __('Deleted.'));
    }
}
