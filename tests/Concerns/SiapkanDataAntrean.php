<?php

namespace Tests\Concerns;

use App\Models\JamOperasional;
use App\Models\KuotaAntrean;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Pembuat data uji yang mengikuti kebutuhan aturan bisnis:
 * - jam operasional dibuat 00:00–23:59 setiap hari supaya pengujian
 *   tidak bergantung pada waktu pengujian;
 * - nomor antrean, kuota, dan tanggal selalu relatif terhadap hari ini.
 */
trait SiapkanDataAntrean
{
    /** Urutan poli dibuat dalam satu test, untuk kode poli yang deterministik. */
    private int $urutanPoli = 0;

    /**
     * Kode poli otomatis: poli pertama "PU", berikutnya "PU2", "PU3", …,
     * sehingga nomor antrean (PU-0001, PU2-0001, …) dapat diprediksi test.
     */
    protected function buatPoli(array $atribut = [], bool $selaluBuka = true): Poli
    {
        $atribut['kode_poli'] ??= 'PU' . ($this->urutanPoli === 0 ? '' : $this->urutanPoli + 1);
        $this->urutanPoli++;

        $poli = Poli::factory()->create($atribut);

        if ($selaluBuka) {
            foreach (JamOperasional::HARI as $hari) {
                JamOperasional::create([
                    'poli_id' => $poli->id,
                    'hari' => $hari,
                    'jam_buka' => '00:00:00',
                    'jam_tutup' => '23:59:00',
                ]);
            }
        }

        return $poli->fresh();
    }

    protected function buatKuota(Poli $poli, int $jumlah, ?string $tanggal = null): KuotaAntrean
    {
        return KuotaAntrean::create([
            'poli_id' => $poli->id,
            'jumlah_kuota' => $jumlah,
            'tanggal_berlaku' => $tanggal ?? Carbon::today()->toDateString(),
        ]);
    }

    protected function buatPasien(array $atribut = []): User
    {
        return User::factory()->pasien()->create($atribut);
    }

    protected function buatPetugas(array $atribut = []): User
    {
        return User::factory()->petugas()->create($atribut);
    }

    protected function buatAdmin(array $atribut = []): User
    {
        return User::factory()->admin()->create($atribut);
    }
}