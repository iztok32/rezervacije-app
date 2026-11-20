<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Storitve s trajanjem (način "service"). */
class ServiceController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:5000',
        'duration_minutes' => 'required|integer|min:5|max:1440',
        'buffer_minutes' => 'required|integer|min:0|max:480',
        'price' => 'nullable|numeric|min:0|max:99999999',
        'is_active' => 'boolean',
        'resource_ids' => 'nullable|array',
        'resource_ids.*' => 'integer',
    ];

    public function store(Request $request, ResourceType $resourceType): RedirectResponse
    {
        $validated = $request->validate(self::RULES);

        $service = Service::create(collect($validated)->except('resource_ids')->all() + [
            'resource_type_id' => $resourceType->id,
            'display_order' => (int) Service::where('resource_type_id', $resourceType->id)->max('display_order') + 1,
        ]);
        $this->syncResources($service, $validated['resource_ids'] ?? []);

        return back()->with('success', __('Saved.'));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate(self::RULES);

        $service->update(collect($validated)->except('resource_ids')->all());
        $this->syncResources($service, $validated['resource_ids'] ?? []);

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return back()->with('success', __('Deleted.'));
    }

    /** Samo viri istega tipa. */
    private function syncResources(Service $service, array $ids): void
    {
        $service->resources()->sync(
            Resource::where('resource_type_id', $service->resource_type_id)->whereIn('id', $ids)->pluck('id')
        );
    }
}
