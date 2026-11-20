<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Viri tipa (konji, frizerji, avtomobili, hiške, sobe …). */
class ResourceController extends Controller
{
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'capacity' => 'required|integer|min:1|max:1000',
            'max_guests' => 'nullable|integer|min:1|max:1000',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'user_id' => 'nullable|integer|exists:users,id',
            'properties' => 'nullable|array|max:20',
            'properties.*.label' => 'required|string|max:60',
            'properties.*.value' => 'required|string|max:255',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:5120',
            'remove_image' => 'boolean',
        ];
    }

    public function store(Request $request, ResourceType $resourceType): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $resource = new Resource($this->attributes($validated));
        $resource->resource_type_id = $resourceType->id;
        $resource->display_order = (int) Resource::where('resource_type_id', $resourceType->id)->max('display_order') + 1;
        $this->handleImage($request, $resource, $validated);
        $resource->save();

        return back()->with('success', __('Saved.'));
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $resource->fill($this->attributes($validated));
        $this->handleImage($request, $resource, $validated);
        $resource->save();

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Resource $resource): RedirectResponse
    {
        if (Booking::where('resource_id', $resource->id)->active()->where('ends_at', '>=', now())->exists()) {
            return back()->with('error', __(':name has upcoming bookings. Deactivate it instead.', ['name' => $resource->name]));
        }

        $resource->delete();

        return back()->with('success', __('Deleted.'));
    }

    private function attributes(array $validated): array
    {
        $data = collect($validated)->except(['image', 'remove_image'])->all();
        $data['properties'] = array_values($validated['properties'] ?? []);

        return $data;
    }

    private function handleImage(Request $request, Resource $resource, array $validated): void
    {
        if (($validated['remove_image'] ?? false) || $request->hasFile('image')) {
            if ($resource->image_path) {
                Storage::disk('public')->delete($resource->image_path);
            }
            $resource->image_path = null;
        }

        if ($request->hasFile('image')) {
            $resource->image_path = $request->file('image')->store('resources', 'public');
        }
    }
}
