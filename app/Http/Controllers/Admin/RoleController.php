<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ResourceType;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vloge osebja: pravice, doseg rezervacij in omejitev na tipe virov.
 * Administratorske vloge ni mogoče urejati ali izbrisati (vedno ima vse pravice).
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::ordered()
            ->with('resourceTypes:id')
            ->withCount('users')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'is_admin' => $r->is_admin,
                'permissions' => $r->is_admin ? Permission::values() : array_values($r->permissions ?? []),
                'booking_scope' => $r->booking_scope,
                'resource_type_ids' => $r->resourceTypes->pluck('id')->all(),
                'users_count' => $r->users_count,
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::options(),
            'types' => ResourceType::ordered()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated) {
            $role = Role::create($this->attributes($validated) + [
                'display_order' => (int) Role::max('display_order') + 1,
            ]);
            $role->resourceTypes()->sync($validated['resource_type_ids'] ?? []);
        });

        return back()->with('success', __('Saved.'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->is_admin) {
            return back()->with('error', __('The administrator role always has all permissions and cannot be changed.'));
        }

        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($role, $validated) {
            $role->update($this->attributes($validated));
            $role->resourceTypes()->sync($validated['resource_type_ids'] ?? []);
        });

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_admin) {
            return back()->with('error', __('The administrator role cannot be deleted.'));
        }
        if ($role->users()->exists()) {
            return back()->with('error', __('This role is assigned to users. Assign them a different role first.'));
        }

        $role->delete();

        return back()->with('success', __('Deleted.'));
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'present|array',
            'permissions.*' => ['string', Rule::in(Permission::values())],
            'booking_scope' => ['required', Rule::in([Role::SCOPE_ALL, Role::SCOPE_OWN])],
            'resource_type_ids' => 'nullable|array',
            'resource_type_ids.*' => 'integer|exists:resource_types,id',
        ];
    }

    private function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => array_values(array_unique($validated['permissions'])),
            'booking_scope' => $validated['booking_scope'],
        ];
    }
}
