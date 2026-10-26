<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Password default: "password" (hash bcrypt — lihat cast 'hashed' pada model).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'peran' => User::PERAN_PASIEN,
            'remember_token' => Str::random(10),
        ];
    }

    public function pasien(): static
    {
        return $this->state(fn () => ['peran' => User::PERAN_PASIEN]);
    }

    public function petugas(): static
    {
        return $this->state(fn () => ['peran' => User::PERAN_PETUGAS]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['peran' => User::PERAN_ADMIN]);
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