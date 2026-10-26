<?php

namespace Database\Factories;

use App\Models\Poli;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Poli>
 */
class PoliFactory extends Factory
{
    protected $model = Poli::class;

    public function definition(): array
    {
        return [
            'kode_poli' => strtoupper(fake()->unique()->lexify('???')),
            'nama_poli' => 'Poli ' . fake()->unique()->word(),
            'deskripsi' => fake()->sentence(),
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['status_aktif' => false]);
    }
}