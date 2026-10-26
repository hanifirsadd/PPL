<?php

namespace Tests\Feature;

use App\Enums\StatusAntrean;
use App\Enums\TipeAntrean;
use App\Models\Antrean;
use App\Models\Poli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * US-04 — Pengambilan antrean online, termasuk prakounsinya:
 * poli aktif (US-03), jam operasional, kuota harian (US-18),
 * dan KNF-01 (maksimal satu antrean aktif per pasien per poli per hari).
 */
class AmbilAntreanTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    public function test_pasien_berhasil_ambil_antrean_pertama(): void
    {
        $pasien = $this->buatPasien();
        $poli = $this->buatPoli();

        $response = $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $response->assertRedirect(route('pasien.status'))
            ->assertSessionHas('sukses', 'Nomor antrean PU-0001 berhasil diambil.');

        $antrean = Antrean::sole();
        $this->assertSame('PU-0001', $antrean->nomor_antrean);
        $this->assertSame($pasien->id, $antrean->user_id);
        $this->assertSame($poli->id, $antrean->poli_id);
        $this->assertSame(TipeAntrean::ONLINE, $antrean->tipe);
        $this->assertSame(StatusAntrean::MENUNGGU, $antrean->status);
        $this->assertTrue($antrean->tanggal_antrean->isToday());
    }

    public function test_nomor_antrean_berikutnya_naik(): void
    {
        $poli = $this->buatPoli();

        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $this->assertSame(
            ['PU-0001', 'PU-0002', 'PU-0003'],
            Antrean::orderBy('nomor_antrean')->pluck('nomor_antrean')->all(),
        );
    }

    /** KNF-01 — satu antrean aktif per pasien per poli per hari. */
    public function test_pasien_dilarang_ambil_antrean_kedua_di_poli_yang_sama(): void
    {
        $pasien = $this->buatPasien();
        $poli = $this->buatPoli();

        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $response = $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $response->assertSessionHasErrors('antrean');
        $this->assertSame(1, Antrean::count());
    }

    /** KNF-01 — setelah selesai, pasien boleh mengambil antrean lagi. */
    public function test_antrean_yang_sudah_selesai_tidak_menghalangi_pengambilan_baru(): void
    {
        $pasien = $this->buatPasien();
        $poli = $this->buatPoli();

        Antrean::factory()->selesai()->create([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id])
            ->assertRedirect(route('pasien.status'));

        $this->assertSame(2, Antrean::count());
    }

    /** KNF-01 — antrean di poli berbeda tetap diperbolehkan. */
    public function test_pasien_boleh_ambil_di_poli_berbeda(): void
    {
        $pasien = $this->buatPasien();
        $poliA = $this->buatPoli();
        $poliB = $this->buatPoli();

        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poliA->id]);
        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poliB->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Antrean::count());
    }

    public function test_ditolak_saat_kuota_poli_habis(): void
    {
        $poli = $this->buatPoli();
        $this->buatKuota($poli, 2);

        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $response = $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $response->assertSessionHasErrors('antrean');
        $this->assertSame(2, Antrean::count());
    }

    public function test_ditolak_untuk_poli_tidak_aktif(): void
    {
        $poli = $this->buatPoli(['status_aktif' => false]);

        $response = $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $response->assertSessionHasErrors('antrean');
        $this->assertDatabaseCount('antrean', 0);
    }

    public function test_ditolak_di_luar_jam_operasional(): void
    {
        $poli = $this->buatPoli(selaluBuka: false);

        $response = $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $response->assertSessionHasErrors('antrean');
        $this->assertDatabaseCount('antrean', 0);
    }

    public function test_halaman_poli_menampilkan_daftar_poli_aktif(): void
    {
        $aktif = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPoli(['nama_poli' => 'Poli Gigi', 'status_aktif' => false]);

        $this->actingAs($this->buatPasien())
            ->get(route('pasien.poli'))
            ->assertOk()
            ->assertSee('Poli Umum')
            ->assertDontSee('Poli Gigi');
    }

    public function test_halaman_status_menampilkan_posisi_dan_nomor_yang_dilayani(): void
    {
        $pasien = $this->buatPasien();
        $poli = $this->buatPoli();

        Antrean::factory()->dilayani()->create([
            'poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001', 'user_id' => null,
        ]);
        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $this->actingAs($pasien)
            ->get(route('pasien.status'))
            ->assertOk()
            ->assertSee('PU-0002')
            ->assertSee('Antrean di depan Anda');

        $data = app(\App\Services\AntreanService::class)->statusPasien($pasien);
        // PU-0001 sedang dilayani sehingga tetap terhitung satu antrean di depan.
        $this->assertSame(1, $data['sisa_di_depan']);
        $this->assertSame('PU-0001', $data['nomor_sedang_dilayani']);
        $this->assertSame(2, $data['jumlah_total']);
    }

    public function test_riwayat_menampilkan_seluruh_antrean_pasien(): void
    {
        $pasien = $this->buatPasien();
        $poli = $this->buatPoli();

        Antrean::factory()->selesai()->create([
            'user_id' => $pasien->id, 'poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001',
            'tanggal_antrean' => Carbon::today()->subDay()->toDateString(),
        ]);

        $this->actingAs($pasien)->get(route('pasien.riwayat'))
            ->assertOk()
            ->assertSee('PU-0001')
            ->assertSee('Selesai');
    }

    public function test_pasien_hanya_melihat_antrean_miliknya(): void
    {
        $poli = $this->buatPoli();
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);

        $pasien = $this->buatPasien();
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002', 'user_id' => $pasien->id]);

        $this->actingAs($pasien)->get(route('pasien.riwayat'))
            ->assertOk()
            ->assertSee('PU-0002')
            ->assertDontSee('PU-0001');
    }

    public function test_halaman_status_tanpa_antrean_menampilkan_petunjuk(): void
    {
        $this->actingAs($this->buatPasien())
            ->get(route('pasien.status'))
            ->assertOk()
            ->assertSee('Anda belum mengambil nomor antrean hari ini');
    }

    public function test_tombol_ambil_antrean_menolak_poli_yang_tertutup(): void
    {
        $this->buatPoli(['nama_poli' => 'Poli Tutup'], selaluBuka: false);

        $this->actingAs($this->buatPasien())
            ->get(route('pasien.poli'))
            ->assertOk()
            ->assertSee('Poli Tutup')
            ->assertSee('Tutup');
    }

    public function test_poli_hanya_tampil_aktif_di_halaman_pasien(): void
    {
        Poli::factory()->create(['nama_poli' => 'Poli Umum']);
        Poli::factory()->nonaktif()->create(['nama_poli' => 'Poli KIA']);

        $this->actingAs($this->buatPasien())
            ->get(route('pasien.poli'))
            ->assertSee('Poli Umum')
            ->assertDontSee('Poli KIA');
    }

    /* -------------------- Penyegaran otomatis (AJAX) --------------------- */

    /** Fragmen status: posisi pasien ikut berubah saat petugas memanggil. */
    public function test_fragmen_status_menampilkan_perubahan_antrean(): void
    {
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien();

        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $this->actingAs($pasien)
            ->get(route('pasien.status.segarkan'))
            ->assertOk()
            ->assertSee('PU-0001')
            ->assertSee('Menunggu');

        // Petugas memanggil nomor tersebut → fragmen harus menampilkan Dipanggil.
        $this->actingAs($this->buatPetugas())->post(route('petugas.panggil'), ['id_poli' => $poli->id]);

        $this->actingAs($pasien)
            ->get(route('pasien.status.segarkan'))
            ->assertOk()
            ->assertSee('Dipanggil');
    }

    public function test_fragmen_status_tanpa_antrean_menampilkan_petunjuk(): void
    {
        $this->actingAs($this->buatPasien())
            ->get(route('pasien.status.segarkan'))
            ->assertOk()
            ->assertSee('Anda belum mengambil nomor antrean hari ini');
    }

    public function test_fragmen_status_hanya_untuk_pasien(): void
    {
        $this->get(route('pasien.status.segarkan'))->assertRedirect(route('login'));

        $this->actingAs($this->buatPetugas())->get(route('pasien.status.segarkan'))->assertForbidden();
        $this->actingAs($this->buatPasien())->get(route('pasien.status.segarkan'))->assertOk();
    }
}