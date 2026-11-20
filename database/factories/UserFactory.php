<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** Sistemske vloge iz migracije: admin, reception, provider. */
    public function withRole(string $slug): static
    {
        return $this->state(fn () => ['role_id' => Role::bySlug($slug)?->id]);
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    /** Recepcija: vse rezervacije, rezervacije za stranke, zaprtja. */
    public function staff(): static
    {
        return $this->withRole('reception');
    }

    /** Izvajalec: samo rezervacije svojih virov. */
    public function provider(): static
    {
        return $this->withRole('provider');
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
