<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'role_id', 'is_active', 'locale', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Enake privzete vrednosti kot v bazi, da jih ima tudi še neosvežen model. */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @var int[]|null */
    private ?array $groupIdsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Vloga osebja; stranka je nima. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Viri, ki jih ta uporabnik predstavlja (npr. frizer). */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->role?->is_admin;
    }

    /** Član osebja (ima vlogo) — dostop do administracije v obsegu svojih pravic. */
    public function isStaff(): bool
    {
        return $this->role_id !== null;
    }

    public function hasPermission(Permission $permission): bool
    {
        return (bool) $this->role?->allows($permission);
    }

    /** @return string[] pravice za vmesnik */
    public function permissionList(): array
    {
        return array_values(array_map(
            fn (Permission $p) => $p->value,
            array_filter(Permission::cases(), fn (Permission $p) => $this->hasPermission($p)),
        ));
    }

    /** Vidi le rezervacije virov, ki jih predstavlja (npr. frizerka svoje stranke). */
    public function seesOnlyOwnBookings(): bool
    {
        return ! $this->isAdmin() && $this->role?->booking_scope === Role::SCOPE_OWN;
    }

    /** @return int[]|null tipi virov, omejeni z vlogo; null = vsi */
    public function allowedResourceTypeIds(): ?array
    {
        if (! $this->role || $this->isAdmin()) {
            return null;
        }

        $ids = $this->role->relationLoaded('resourceTypes')
            ? $this->role->resourceTypes->pluck('id')
            : $this->role->resourceTypes()->pluck('resource_types.id');

        return $ids->isEmpty() ? null : $ids->map(fn ($id) => (int) $id)->all();
    }

    public function canAccessType(ResourceType|int $type): bool
    {
        $allowed = $this->allowedResourceTypeIds();

        return $allowed === null || in_array($type instanceof ResourceType ? $type->id : $type, $allowed, true);
    }

    /** Osebje, ki sme za stranko rezervirati ta tip (mimo okna za rezervacije in omejitev skupin). */
    public function canBookForOthers(ResourceType $type): bool
    {
        return $this->hasPermission(Permission::BookingsCreate) && $this->canAccessType($type);
    }

    /** @return int[] */
    public function customerGroupIds(): array
    {
        return $this->groupIdsCache ??= $this->customerGroups()->pluck('customer_groups.id')->map(fn ($id) => (int) $id)->all();
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }
}
