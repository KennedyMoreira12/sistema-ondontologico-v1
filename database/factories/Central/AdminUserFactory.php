<?php

namespace Database\Factories\Central;

use App\Models\Central\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdminUser>
 */
class AdminUserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Quem entra no painel para responder cliente, sem mexer em plano e
     * cobrança.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'support',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
