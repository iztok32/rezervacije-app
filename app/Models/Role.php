<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vloga osebja: nabor pravic, doseg rezervacij (vse / samo svoji viri) in neobvezna omejitev
 * na izbrane tipe virov. Administratorska vloga (is_admin) ima vedno vse.
 */
class Role extends Model
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_OWN = 'own';

    protected $fillable = ['slug', 'name', 'description', 'is_admin', 'permissions', 'booking_scope', 'display_order'];

    protected $attributes = [
        'permissions' => '[]',
        'booking_scope' => self::SCOPE_ALL,
        'is_admin' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'permissions' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Tipi virov, na katere je vloga omejena; prazno = vsi. */
    public function resourceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ResourceType::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function allows(Permission $permission): bool
    {
        return $this->is_admin || in_array($permission->value, $this->permissions ?? [], true);
    }

    public static function admin(): ?self
    {
        return static::where('is_admin', true)->orderBy('id')->first();
    }

    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
