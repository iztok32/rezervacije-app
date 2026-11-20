<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Uporabniki in vloge (administrator). */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'role' => ['nullable', Rule::enum(Role::class)],
        ]);

        $users = User::query()
            ->withCount('bookings')
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
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
                    'role' => $u->role->value,
                    'is_active' => $u->is_active,
                    'bookings_count' => $u->bookings_count,
                    'created_at' => $u->created_at?->toDateString(),
                ]),
                'links' => $users->linkCollection(),
            ],
            'filters' => $filters,
            'roles' => Role::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $user = User::create($validated + ['password' => Str::password(32)]);
        $user->forceFill(['email_verified_at' => now()])->save();

        // Uporabnik si geslo nastavi prek povezave v e-pošti
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', __('User created. A link to set the password was sent to :email.', ['email' => $user->email]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate($this->rules($user));

        // Administrator si ne more sam odvzeti pravic ali se deaktivirati
        if ($user->is($request->user()) && ($validated['role'] !== Role::Admin->value || ! $validated['is_active'])) {
            return back()->with('error', __('You cannot remove your own administrator access.'));
        }

        $user->update($validated);

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('You cannot delete your own account here.'));
        }

        // Rezervacije ostanejo (podatki o stranki so shranjeni na rezervaciji)
        $user->delete();

        return back()->with('success', __('Deleted.'));
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => 'nullable|string|max:40',
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => 'boolean',
        ];
    }
}
