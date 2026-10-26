<?php

namespace Tests\Feature;

use App\Enums\StatusAntrean;
use App\Enums\TipeAntrean;
use App\Models\Antrean;
use App\Models\Poli;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * KNF-01 dijamin bukan hanya oleh aplikasi, tetapi juga oleh constraint
 * database. Test ini menyisipkan baris langsung (melewati service) untuk
 * membuktikan bahwa basis data sendiri menolak pelanggaran aturan.
 */
class KonstrainDatabaseTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    public function test_database_menolak_dua_antrean_aktif_untuk_pasien_yang_sama(): void
    {
        $pasien = $this->buatPasien();
        $poli = Poli::factory()->create();

        $this->sisipkan(['user_id' => $pasien->id, 'poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);

        $this->expectException(QueryException::class);
        $this->sisipkan(['user_id' => $pasien->id, 'poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);
    }

    public function test_database_menolak_nomor_antrean_ganda(): void
    {
        $poli = Poli::factory()->create();

        $this->sisipkan(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);

        $this->expectException(QueryException::class);
        $this->sisipkan(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
    }

    public function test_database_menolak_dua_pasien_dilayani_bersamaan_di_satu_poli(): void
    {
        $poli = Poli::factory()->create();

        $this->sisipkan([
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
            'status' => StatusAntrean::DILAYANI->value,
            'waktu_dipanggil' => now(),
            'waktu_mulai' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->sisipkan([
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0002',
            'status' => StatusAntrean::DILAYANI->value,
            'waktu_dipanggil' => now(),
            'waktu_mulai' => now(),
        ]);
    }

    public function test_database_menolak_antrean_tanpa_identitas_pasien(): void
    {
        $poli = Poli::factory()->create();

        $this->expectException(QueryException::class);
        $this->sisipkan(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001', 'user_id' => null, 'nama_pasien_manual' => null]);
    }

    public function test_database_menolak_status_di_dalam_timestamps_bersesuaian(): void
    {
        $poli = Poli::factory()->create();

        // Status SELESAI tanpa waktu_selesai melanggar constraint integritas.
        $this->expectException(QueryException::class);
        $this->sisipkan([
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
            'status' => StatusAntrean::SELESAI->value,
            'waktu_dipanggil' => now(),
            'waktu_mulai' => now(),
        ]);
    }

    public function test_antrean_yang_sudah_selesai_tidak_mengunci_pasien(): void
    {
        $pasien = $this->buatPasien();
        $poli = Poli::factory()->create();

        $this->sisipkan([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
            'status' => StatusAntrean::SELESAI->value,
            'waktu_dipanggil' => now(),
            'waktu_mulai' => now(),
            'waktu_selesai' => now(),
        ]);

        // Tidak seharusnya melempar exception.
        $kedua = $this->sisipkan(['user_id' => $pasien->id, 'poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);
        $this->assertNotNull($kedua->id);
    }

    public function test_antrean_walk_in_boleh_berulang(): void
    {
        $poli = Poli::factory()->create();

        // Tanpa akun (walk-in) tidak ada user_id, sehingga kunci_aktif bernilai NULL
        // dan index unik tidak bentrok — sesuai desain ERD.
        $this->sisipkan(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001', 'nama_pasien_manual' => 'Warga A']);
        $kedua = $this->sisipkan(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002', 'nama_pasien_manual' => 'Warga B']);

        $this->assertNotNull($kedua->id);
    }

    /** Sisipkan baris antrean secara langsung, melewati AntreanService. */
    private function sisipkan(array $atribut): Antrean
    {
        $baris = DB::table('antrean')->insertGetId(array_merge([
            'nomor_antrean' => 'PU-0001',
            'user_id' => null,
            'nama_pasien_manual' => 'Warga Walk-in',
            'poli_id' => Poli::factory()->create()->id,
            'dibuat_oleh' => null,
            'tanggal_antrean' => now()->toDateString(),
            'tipe' => TipeAntrean::MANUAL->value,
            'status' => StatusAntrean::MENUNGGU->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], $atribut));

        return Antrean::findOrFail($baris);
    }
}