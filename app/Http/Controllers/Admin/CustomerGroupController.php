<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\ResourceType;
use App\Models\Timeslot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Skupine strank; člane določimo pri uporabniku, dostop pri tipu vira ali terminu. */
class CustomerGroupController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:100',
        'description' => 'nullable|string|max:500',
        'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
    ];

    public function index(): Response
    {
        $groups = CustomerGroup::ordered()
            ->withCount('users')
            ->with([
                'resourceTypes:id,name',
                'timeslots' => fn ($q) => $q->with('type:id,name')->select('timeslots.id', 'timeslots.name', 'timeslots.resource_type_id'),
            ])
            ->get()
            ->map(fn (CustomerGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'description' => $g->description,
                'color' => $g->color,
                'users_count' => $g->users_count,
                // Kje je skupina uporabljena: tipi virov in termini z omejenim dostopom
                'restricts' => $g->resourceTypes->map(fn (ResourceType $t) => $t->name)
                    ->merge($g->timeslots->map(fn (Timeslot $s) => trim(($s->type?->name ? $s->type->name.' · ' : '').$s->name)))
                    ->values(),
            ]);

        return Inertia::render('Admin/CustomerGroups/Index', ['groups' => $groups]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(self::RULES);

        CustomerGroup::create($validated + ['display_order' => (int) CustomerGroup::max('display_order') + 1]);

        return back()->with('success', __('Saved.'));
    }

    public function update(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $customerGroup->update($request->validate(self::RULES));

        return back()->with('success', __('Saved.'));
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        // Brez te zaščite bi ponudba, omejena samo na to skupino, po izbrisu postala odprta za vse
        if ($customerGroup->resourceTypes()->exists() || $customerGroup->timeslots()->exists()) {
            return back()->with('error', __('This group restricts access to the offer. Remove it there first.'));
        }

        $customerGroup->delete();

        return back()->with('success', __('Deleted.'));
    }
}
