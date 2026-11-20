<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'role', 'is_active', 'locale', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Enake privzete vrednosti kot v bazi, da jih ima tudi še neosvežen model. */
    protected $attributes = [
        'role' => 'customer',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
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
        return $this->role === Role::Admin;
    }

    /** Osebje ali admin — upravlja rezervacije. */
    public function isStaff(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Staff], true);
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }
}
