<?php

namespace Database\Factories;

use App\Enums\StatusAntrean;
use App\Enums\TipeAntrean;
use App\Models\Antrean;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Antrean>
 */
class AntreanFactory extends Factory
{
    protected $model = Antrean::class;

    public function definition(): array
    {
        return [
            // Nomor berawalan kode poli, mis. PU-0001 (lihat AntreanService).
            'nomor_antrean' => strtoupper(fake()->unique()->lexify('???')) . '-' . fake()->unique()->numerify('####'),
            'user_id' => null,
            'nama_pasien_manual' => fake()->name(),
            'poli_id' => \App\Models\Poli::factory(),
            'dibuat_oleh' => null,
            'tanggal_antrean' => now()->toDateString(),
            'tipe' => TipeAntrean::MANUAL->value,
            'status' => StatusAntrean::MENUNGGU->value,
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => ['tipe' => TipeAntrean::ONLINE->value]);
    }

    public function status(StatusAntrean $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }

    public function dipanggil(): static
    {
        return $this->state(fn () => [
            'status' => StatusAntrean::DIPANGGIL->value,
            'waktu_dipanggil' => now(),
        ]);
    }

    public function dilayani(): static
    {
        return $this->state(fn () => [
            'status' => StatusAntrean::DILAYANI->value,
            'waktu_dipanggil' => now()->subMinutes(5),
            'waktu_mulai' => now(),
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn () => [
            'status' => StatusAntrean::SELESAI->value,
            'waktu_dipanggil' => now()->subMinutes(10),
            'waktu_mulai' => now()->subMinutes(8),
            'waktu_selesai' => now(),
        ]);
    }

    public function dilewati(): static
    {
        return $this->state(fn () => [
            'status' => StatusAntrean::DILEWATI->value,
            'waktu_dipanggil' => now()->subMinutes(5),
        ]);
    }
}