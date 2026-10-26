<?php

namespace Tests\Feature;

use App\Enums\StatusAntrean;
use App\Models\Antrean;
use App\Models\Poli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * US-10 s/d US-15 — pengelolaan antrean oleh petugas:
 * panggil, mulai layanan, selesaikan, lewati, dan panggil ulang.
 * Status akhir mengikuti state machine (MENUNGGU → DIPANGGIL → DILAYANI →
 * SELESAI; DIPANGGIL → DILEWATI → DIPANGGIL).
 */
class PengelolaanAntreanTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    public function test_daftar_antrean_hari_ini_dapat_disaring_per_poli_dan_status(): void
    {
        $poliA = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $poliB = $this->buatPoli(['nama_poli' => 'Poli Gigi']);

        Antrean::factory()->create(['poli_id' => $poliA->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->dilayani()->create(['poli_id' => $poliA->id, 'nomor_antrean' => 'PU-0002']);
        Antrean::factory()->create(['poli_id' => $poliB->id, 'nomor_antrean' => 'PU2-0001']);

        $petugas = $this->buatPetugas();

        $this->actingAs($petugas)->get(route('petugas.antrean'))->assertOk();

        $semua = $this->actingAs($petugas)->get(route('petugas.antrean', ['poli' => $poliA->id]));
        $semua->assertOk();
        $this->assertCount(2, $semua->viewData('daftar'));

        $hanyaMenunggu = $this->actingAs($petugas)
            ->get(route('petugas.antrean', ['poli' => $poliA->id, 'status' => StatusAntrean::MENUNGGU->value]));
        $this->assertCount(1, $hanyaMenunggu->viewData('daftar'));
    }

    /** US-11 — memanggil pasien teratas (nomor terkecil berstatus MENUNGGU). */
    public function test_panggil_pasien_teratas(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        Antrean::factory()->selesai()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        $target = Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0003']);

        $this->actingAs($petugas)
            ->post(route('petugas.panggil'), ['id_poli' => $poli->id])
            ->assertSessionHas('sukses', 'Memanggil nomor PU-0002.');

        $target->refresh();
        $this->assertSame(StatusAntrean::DIPANGGIL, $target->status);
        $this->assertNotNull($target->waktu_dipanggil);
    }

    public function test_panggil_ditolak_bila_tidak_ada_yang_menunggu(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        $this->actingAs($petugas)
            ->post(route('petugas.panggil'), ['id_poli' => $poli->id])
            ->assertSessionHasErrors('antrean');
    }

    /** Tombol Panggil pada baris tertentu (mis. walk-in atau urutan khusus). */
    public function test_panggil_spesifik_pada_baris_tersentu(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        $target = Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0003']);

        $this->actingAs($petugas)
            ->post(route('petugas.panggil.spesifik', $target->id))
            ->assertSessionHas('sukses', 'Memanggil nomor PU-0002.');

        $target->refresh();
        $this->assertSame(StatusAntrean::DIPANGGIL, $target->status);
        $this->assertNotNull($target->waktu_dipanggil);
    }

    public function test_panggil_spesifik_membuat_notifikasi(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien();

        $antrean = Antrean::factory()->create([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        $this->actingAs($petugas)->post(route('petugas.panggil.spesifik', $antrean->id));

        $this->assertDatabaseHas('notifikasi', [
            'antrean_id' => $antrean->id,
            'jenis' => \App\Models\Notifikasi::DIPANGGIL,
        ]);
    }

    public function test_panggil_spesifik_ditolak_bila_status_bukan_menunggu(): void
    {
        $petugas = $this->buatPetugas();
        $antrean = Antrean::factory()->selesai()->create();

        $this->actingAs($petugas)
            ->post(route('petugas.panggil.spesifik', $antrean->id))
            ->assertSessionHasErrors('antrean');

        $this->assertSame(StatusAntrean::SELESAI, $antrean->fresh()->status);
    }

    /** US-12 — mulai layanan. */
    public function test_mulai_pelayanan(): void
    {
        $petugas = $this->buatPetugas();
        $antrean = Antrean::factory()->dipanggil()->create();

        $this->actingAs($petugas)
            ->post(route('petugas.mulai', $antrean->id))
            ->assertSessionHas('sukses', "Nomor {$antrean->nomor_antrean} sedang dilayani.");

        $antrean->refresh();
        $this->assertSame(StatusAntrean::DILAYANI, $antrean->status);
        $this->assertNotNull($antrean->waktu_mulai);
    }

    /** US-13 — selesaikan layanan. */
    public function test_selesaikan_pelayanan(): void
    {
        $petugas = $this->buatPetugas();
        $antrean = Antrean::factory()->dilayani()->create();

        $this->actingAs($petugas)->post(route('petugas.selesai', $antrean->id));

        $antrean->refresh();
        $this->assertSame(StatusAntrean::SELESAI, $antrean->status);
        $this->assertNotNull($antrean->waktu_selesai);
    }

    /** US-14 — lewati pasien yang tidak hadir. */
    public function test_lewati_pasien(): void
    {
        $petugas = $this->buatPetugas();
        $antrean = Antrean::factory()->dipanggil()->create();

        $this->actingAs($petugas)->post(route('petugas.lewati', $antrean->id));

        $this->assertSame(StatusAntrean::DILEWATI, $antrean->fresh()->status);
    }

    /** US-15 — panggil ulang pasien yang pernah dilewati. */
    public function test_panggil_ulang_pasien_yang_dilewati(): void
    {
        $petugas = $this->buatPetugas();
        $antrean = Antrean::factory()->dilewati()->create();

        $this->actingAs($petugas)->post(route('petugas.panggil-ulang', $antrean->id));

        $antrean->refresh();
        $this->assertSame(StatusAntrean::DIPANGGIL, $antrean->status);
    }

    public function test_transisi_status_ilegal_ditolak(): void
    {
        $petugas = $this->buatPetugas();

        $menunggu = Antrean::factory()->create();
        $selesai = Antrean::factory()->selesai()->create();

        // MENUNGGU tidak bisa langsung dilayani
        $this->actingAs($petugas)->post(route('petugas.mulai', $menunggu->id))
            ->assertSessionHasErrors('antrean');
        $this->assertSame(StatusAntrean::MENUNGGU, $menunggu->fresh()->status);

        // SELESAI tidak bisa dilayani lagi
        $this->actingAs($petugas)->post(route('petugas.mulai', $selesai->id))
            ->assertSessionHasErrors('antrean');
        $this->actingAs($petugas)->post(route('petugas.lewati', $selesai->id))
            ->assertSessionHasErrors('antrean');
        $this->actingAs($petugas)->post(route('petugas.panggil-ulang', $selesai->id))
            ->assertSessionHasErrors('antrean');
    }

    public function test_hanya_satu_pasien_dapat_dilayani_per_poli(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        $pertama = Antrean::factory()->dipanggil()->create(['poli_id' => $poli->id]);
        $kedua = Antrean::factory()->dipanggil()->create(['poli_id' => $poli->id]);

        $this->actingAs($petugas)->post(route('petugas.mulai', $pertama->id))
            ->assertSessionHasNoErrors();

        $this->actingAs($petugas)->post(route('petugas.mulai', $kedua->id))
            ->assertSessionHasErrors('antrean');

        $this->assertSame(StatusAntrean::DIPANGGIL, $kedua->fresh()->status);
    }

    public function test_alur_lengkap_menunggu_sampai_selesai(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien();

        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $antrean = Antrean::sole();

        $this->actingAs($petugas)->post(route('petugas.panggil'), ['id_poli' => $poli->id]);
        $this->actingAs($petugas)->post(route('petugas.mulai', $antrean->id));
        $this->actingAs($petugas)->post(route('petugas.selesai', $antrean->id));

        $antrean->refresh();
        $this->assertSame(StatusAntrean::SELESAI, $antrean->status);
        $this->assertNotNull($antrean->waktu_selesai);
    }

    public function test_alur_alternatif_lewati_lalu_panggil_ulang(): void
    {
        $petugas = $this->buatPetugas();
        $antrean = Antrean::factory()->create();

        $this->actingAs($petugas)->post(route('petugas.panggil'), ['id_poli' => $antrean->poli_id]);
        $this->actingAs($petugas)->post(route('petugas.lewati', $antrean->id));
        $this->assertSame(StatusAntrean::DILEWATI, $antrean->fresh()->status);

        $this->actingAs($petugas)->post(route('petugas.panggil-ulang', $antrean->id));
        $antrean->refresh();
        $this->assertSame(StatusAntrean::DIPANGGIL, $antrean->status);
        $this->assertNull($antrean->waktu_mulai, 'Pemanggilan ulang belum memulai pelayanan.');

        $this->actingAs($petugas)->post(route('petugas.mulai', $antrean->id));
        $this->assertSame(StatusAntrean::DILAYANI, $antrean->fresh()->status);
    }

    public function test_admin_boleh_mengawasi_daftar_antrean(): void
    {
        $this->actingAs($this->buatAdmin())->get(route('petugas.antrean'))->assertOk();
    }

    public function test_halaman_antrean_tidak_menampilkan_antrean_besok(): void
    {
        $poli = $this->buatPoli();
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->create([
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0002',
            'tanggal_antrean' => now()->addDay()->toDateString(),
        ]);

        $daftar = $this->actingAs($this->buatPetugas())->get(route('petugas.antrean'))->viewData('daftar');

        $this->assertCount(1, $daftar);
        $this->assertSame('PU-0001', $daftar->first()->nomor_antrean);
    }

    /* -------------------- Penyegaran otomatis (AJAX) --------------------- */

    /** Fragmen tabel dipakai penyegaran otomatis agar data tidak basi. */
    public function test_fragmen_daftar_menampilkan_antrean_terbaru(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $petugas = $this->buatPetugas();

        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);

        $this->actingAs($petugas)
            ->get(route('petugas.antrean.fragmen'))
            ->assertOk()
            ->assertSee('PU-0001');

        // Pasien mengambil antrean baru → harus langsung terlihat di fragmen.
        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $this->actingAs($petugas)
            ->get(route('petugas.antrean.fragmen'))
            ->assertOk()
            ->assertSee('PU-0002');
    }

    public function test_fragmen_daftar_mengikuti_filter_poli_dan_status(): void
    {
        $poliA = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $poliB = $this->buatPoli(['nama_poli' => 'Poli Gigi']);
        $petugas = $this->buatPetugas();

        Antrean::factory()->create(['poli_id' => $poliA->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->selesai()->create(['poli_id' => $poliB->id, 'nomor_antrean' => 'PU2-0001']);

        $semua = $this->actingAs($petugas)->get(route('petugas.antrean.fragmen'));
        $semua->assertSee('PU-0001');

        $hanyaMenunggu = $this->actingAs($petugas)->get(route('petugas.antrean.fragmen', [
            'poli' => $poliA->id,
            'status' => StatusAntrean::MENUNGGU->value,
        ]));
        $hanyaMenunggu->assertOk()->assertSee('Menunggu');
    }

    public function test_fragmen_daftar_hanya_untuk_petugas_dan_admin(): void
    {
        // Tamu diperiksa lebih dahulu: sekali actingAs(), sesi tetap terisi
        // untuk request berikutnya di dalam test yang sama.
        $this->get(route('petugas.antrean.fragmen'))->assertRedirect(route('login'));

        $this->actingAs($this->buatPasien())->get(route('petugas.antrean.fragmen'))->assertForbidden();
        $this->actingAs($this->buatPetugas())->get(route('petugas.antrean.fragmen'))->assertOk();
        $this->actingAs($this->buatAdmin())->get(route('petugas.antrean.fragmen'))->assertOk();
    }
}