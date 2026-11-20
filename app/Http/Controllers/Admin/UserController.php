<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uporabniki: stranke in osebje, vloga in članstvo v skupinah strank.
 * Vlogo spreminja samo, kdor ima pravico za vloge; administratorje ureja samo administrator.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'role' => 'nullable|string|max:20', // "customer", "staff" ali id vloge
            'group' => 'nullable|integer',
        ]);
        $actor = $request->user();

        $users = User::query()
            ->with(['role:id,name,is_admin', 'customerGroups:id,name,color'])
            ->withCount('bookings')
            ->when($filters['role'] ?? null, fn ($q, $role) => match ($role) {
                'customer' => $q->whereNull('role_id'),
                'staff' => $q->whereNotNull('role_id'),
                default => $q->where('role_id', (int) $role),
            })
            ->when($filters['group'] ?? null, fn ($q, $group) => $q->whereHas('customerGroups', fn ($g) => $g->whereKey($group)))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]));
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => [
                'data' => collect($users->items())->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'role_id' => $u->role_id,
                    'role' => $u->role?->name,
                    'is_admin' => $u->isAdmin(),
                    'is_active' => $u->is_active,
                    'groups' => $u->customerGroups->map(fn (CustomerGroup $g) => ['id' => $g->id, 'name' => $g->name, 'color' => $g->color])->values(),
                    'bookings_count' => $u->bookings_count,
                    'created_at' => $u->created_at?->toDateString(),
                ]),
                'links' => $users->linkCollection(),
            ],
            'filters' => $filters,
            'roles' => Role::ordered()->get(['id', 'name', 'is_admin']),
            'groups' => CustomerGroup::ordered()->get(['id', 'name', 'color']),
            'canManageRoles' => $actor->hasPermission(Permission::RolesManage),
            'canAssignAdmin' => $actor->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $this->authorizeRole($request->user(), null, isset($validated['role_id']) ? (int) $validated['role_id'] : null);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create(collect($validated)->except('customer_group_ids')->all() + ['password' => Str::password(32)]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->customerGroups()->sync($validated['customer_group_ids'] ?? []);

            return $user;
        });

        // Uporabnik si geslo nastavi prek povezave v e-pošti
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', __('User created. A link to set the password was sent to :email.', ['email' => $user->email]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validate($this->rules($user));
        $roleId = isset($validated['role_id']) ? (int) $validated['role_id'] : null;

        if ($user->is($actor) && ($roleId !== ($user->role_id !== null ? (int) $user->role_id : null) || ! $validated['is_active'])) {
            return back()->with('error', __('You cannot change your own role or deactivate yourself.'));
        }

        $this->authorizeRole($actor, $user, $roleId);

        DB::transaction(function () use ($user, $validated) {
            $user->update(collect($validated)->except('customer_group_ids')->all());
            $user->customerGroups()->sync($validated['customer_group_ids'] ?? []);
        });

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($user->is($actor)) {
            return back()->with('error', __('You cannot delete your own account here.'));
        }
        if ($user->isStaff() && ! $actor->hasPermission(Permission::RolesManage)) {
            return back()->with('error', __('Only users who manage roles can delete staff members.'));
        }
        if ($user->isAdmin() && ! $actor->isAdmin()) {
            return back()->with('error', __('Only an administrator can delete another administrator.'));
        }

        // Rezervacije ostanejo (podatki o stranki so shranjeni na rezervaciji)
        $user->delete();

        return back()->with('success', __('Deleted.'));
    }

    /**
     * Zaščita pred stopnjevanjem pravic: vlogo dodeli/spremeni samo, kdor ureja vloge,
     * administratorsko vlogo (ali administratorja) pa samo administrator.
     */
    private function authorizeRole(User $actor, ?User $target, ?int $roleId): void
    {
        if ($target?->isAdmin() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['role_id' => __('Only an administrator can edit another administrator.')]);
        }

        if ($roleId === ($target?->role_id !== null ? (int) $target->role_id : null)) {
            return;
        }

        if (! $actor->hasPermission(Permission::RolesManage)) {
            throw ValidationException::withMessages(['role_id' => __('You are not allowed to assign roles.')]);
        }

        if ($roleId && Role::whereKey($roleId)->value('is_admin') && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['role_id' => __('Only an administrator can assign the administrator role.')]);
        }
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => 'nullable|string|max:40',
            'role_id' => 'nullable|integer|exists:roles,id',
            'is_active' => 'boolean',
            'customer_group_ids' => 'nullable|array',
            'customer_group_ids.*' => 'integer|exists:customer_groups,id',
        ];
    }
}
