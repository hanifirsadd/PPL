<?php

namespace Tests\Feature;

use App\Models\Antrean;
use App\Models\JamOperasional;
use App\Models\KuotaAntrean;
use App\Models\Notifikasi;
use App\Models\Poli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * Pengurutan (sorting) daftar pada keenam halaman daftar: pasien (poli,
 * riwayat, notifikasi), petugas (antrean), dan admin (poli, jam, kuota).
 *
 * Kriteria: hanya kolom yang diizinkan yang bisa dipilih, kolom tak dikenal
 * diabaikan (tidak menjadi celah), arah asc/desc dapat ditukar, dan filter
 * yang aktif tetap terbawa.
 */
class SortingDaftarTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    /* ------------------------------ Petugas ------------------------------ */

    public function test_daftar_antrean_bisa_diurutkan(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002', 'user_id' => null, 'nama_pasien_manual' => 'Zainal']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001', 'user_id' => null, 'nama_pasien_manual' => 'Budi']);

        $urutNomor = $this->actingAs($petugas)->get(route('petugas.antrean', ['urutkan' => 'nomor']));
        $this->assertSame(
            ['PU-0001', 'PU-0002'],
            $urutNomor->viewData('daftar')->pluck('nomor_antrean')->all(),
        );

        $urutPasien = $this->actingAs($petugas)->get(route('petugas.antrean', ['urutkan' => 'pasien', 'arah' => 'asc']));
        $this->assertSame(
            ['PU-0001', 'PU-0002'],
            $urutPasien->viewData('daftar')->pluck('nomor_antrean')->all(),
            'Nama B < Z harus mendahului pada urutan nama pasien.',
        );
    }

    public function test_daftar_antrean_urut_arah_turun(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);

        $daftar = $this->actingAs($petugas)
            ->get(route('petugas.antrean', ['urutkan' => 'nomor', 'arah' => 'desc']))
            ->viewData('daftar');

        $this->assertSame(['PU-0002', 'PU-0001'], $daftar->pluck('nomor_antrean')->all());
    }

    public function test_urutan_default_dan_kolom_tidak_dikenal(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);

        $default = $this->actingAs($petugas)->get(route('petugas.antrean'));
        $asing = $this->actingAs($petugas)->get(route('petugas.antrean', ['urutkan' => 'password', 'arah' => 'drop table']));

        $this->assertSame(['PU-0001', 'PU-0002'], $default->viewData('daftar')->pluck('nomor_antrean')->all());
        $this->assertSame(['PU-0001', 'PU-0002'], $asing->viewData('daftar')->pluck('nomor_antrean')->all());
    }

    public function test_filter_poli_tetap_berlaku_saat_diurutkan(): void
    {
        $petugas = $this->buatPetugas();
        $poliA = $this->buatPoli();
        $poliB = $this->buatPoli();

        Antrean::factory()->create(['poli_id' => $poliA->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->create(['poli_id' => $poliB->id, 'nomor_antrean' => 'PU2-0001']);

        $daftar = $this->actingAs($petugas)
            ->get(route('petugas.antrean', ['poli' => $poliB->id, 'urutkan' => 'nomor']))
            ->viewData('daftar');

        $this->assertCount(1, $daftar);
        $this->assertSame($poliB->id, $daftar->first()->poli_id);
    }

    /* ------------------------------- Pasien ------------------------------- */

    public function test_kartu_poli_bisa_diurutkan(): void
    {
        $pasien = $this->buatPasien();
        $this->buatPoli(['kode_poli' => 'KIA', 'nama_poli' => 'Poli KIA']);
        $this->buatPoli(['kode_poli' => 'AMN', 'nama_poli' => 'Poli Umum']);

        $lewatNama = $this->actingAs($pasien)->get(route('pasien.poli', ['urutkan' => 'nama']));
        $this->assertSame(
            ['Poli KIA', 'Poli Umum'],
            $lewatNama->viewData('daftarPoli')->pluck('poli.nama_poli')->all(),
        );

        $lewatKode = $this->actingAs($pasien)->get(route('pasien.poli', ['urutkan' => 'kode', 'arah' => 'desc']));
        $this->assertSame(
            ['KIA', 'AMN'],
            $lewatKode->viewData('daftarPoli')->pluck('poli.kode_poli')->all(),
        );
    }

    public function test_riwayat_antrean_bisa_diurutkan(): void
    {
        $pasien = $this->buatPasien();
        $poli = $this->buatPoli();

        Antrean::factory()->create(['user_id' => $pasien->id, 'poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->create([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0002',
            'tanggal_antrean' => now()->subDays(3)->toDateString(),
        ]);

        $default = $this->actingAs($pasien)->get(route('pasien.riwayat'));
        $this->assertSame(
            ['PU-0001', 'PU-0002'],
            $default->viewData('riwayat')->pluck('nomor_antrean')->all(),
            'Riwayat default dari yang terbaru.',
        );

        $byNomor = $this->actingAs($pasien)->get(route('pasien.riwayat', ['urutkan' => 'nomor']));
        $this->assertSame(['PU-0001', 'PU-0002'], $byNomor->viewData('riwayat')->pluck('nomor_antrean')->all());

        $turun = $this->actingAs($pasien)->get(route('pasien.riwayat', ['urutkan' => 'nomor', 'arah' => 'desc']));
        $this->assertSame(['PU-0002', 'PU-0001'], $turun->viewData('riwayat')->pluck('nomor_antrean')->all());
    }

    public function test_notifikasi_bisa_diurutkan(): void
    {
        $pasien = $this->buatPasien();
        $antrean = Antrean::factory()->create(['user_id' => $pasien->id]);

        $lama = Notifikasi::create([
            'antrean_id' => $antrean->id, 'user_id' => $pasien->id,
            'jenis' => Notifikasi::DIPANGGIL, 'pesan' => 'Dipanggil',
        ]);
        $lama->forceFill(['created_at' => now()->subHour()])->save();

        $baru = Notifikasi::create([
            'antrean_id' => $antrean->id, 'user_id' => $pasien->id,
            'jenis' => Notifikasi::REMINDER_TIGA, 'pesan' => 'Pengingat',
        ]);
        $baru->forceFill(['created_at' => now()])->save();

        $default = $this->actingAs($pasien)->get(route('pasien.notifikasi'));
        $this->assertSame(Notifikasi::REMINDER_TIGA, $default->viewData('daftar')->first()->jenis);

        // Kolom `jenis` bertipe ENUM, jadi urutannya mengikuti urutan deklarasi
        // (REMINDER_3_ANTREAN lebih dulu), bukan urutan alfabetis.
        $byJenis = $this->actingAs($pasien)->get(route('pasien.notifikasi', ['urutkan' => 'jenis']));
        $this->assertSame(Notifikasi::REMINDER_TIGA, $byJenis->viewData('daftar')->first()->jenis);

        $byJenisTurun = $this->actingAs($pasien)->get(route('pasien.notifikasi', ['urutkan' => 'jenis', 'arah' => 'desc']));
        $this->assertSame(Notifikasi::DIPANGGIL, $byJenisTurun->viewData('daftar')->first()->jenis);
    }

    /* -------------------------------- Admin ------------------------------- */

    public function test_daftar_poli_admin_bisa_diurutkan(): void
    {
        $admin = $this->buatAdmin();
        Poli::factory()->create(['kode_poli' => 'ZZZ', 'nama_poli' => 'Poli Gigi']);
        Poli::factory()->create(['kode_poli' => 'AAA', 'nama_poli' => 'Poli Anak']);

        $default = $this->actingAs($admin)->get(route('admin.poli'));
        $this->assertSame(
            ['Poli Anak', 'Poli Gigi'],
            $default->viewData('daftarPoli')->pluck('nama_poli')->all(),
        );

        $byKode = $this->actingAs($admin)->get(route('admin.poli', ['urutkan' => 'kode']));
        $this->assertSame(['AAA', 'ZZZ'], $byKode->viewData('daftarPoli')->pluck('kode_poli')->all());
    }

    public function test_daftar_kuota_bisa_diurutkan(): void
    {
        $admin = $this->buatAdmin();
        $poliA = $this->buatPoli();
        $poliB = $this->buatPoli();

        KuotaAntrean::create(['poli_id' => $poliA->id, 'jumlah_kuota' => 10, 'tanggal_berlaku' => now()->addDay()->toDateString()]);
        KuotaAntrean::create(['poli_id' => $poliB->id, 'jumlah_kuota' => 90, 'tanggal_berlaku' => now()->addDay()->toDateString()]);

        $byJumlah = $this->actingAs($admin)->get(route('admin.kuota', ['urutkan' => 'jumlah']));
        $this->assertSame([10, 90], $byJumlah->viewData('daftarKuota')->pluck('jumlah_kuota')->all());

        $turun = $this->actingAs($admin)->get(route('admin.kuota', ['urutkan' => 'jumlah', 'arah' => 'desc']));
        $this->assertSame([90, 10], $turun->viewData('daftarKuota')->pluck('jumlah_kuota')->all());
    }

    public function test_jam_operasional_berurutan_hari(): void
    {
        $admin = $this->buatAdmin();
        $poli = $this->buatPoli([], selaluBuka: false);

        JamOperasional::create(['poli_id' => $poli->id, 'hari' => 'SABTU', 'jam_buka' => '08:00', 'jam_tutup' => '12:00']);
        JamOperasional::create(['poli_id' => $poli->id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '14:00']);
        JamOperasional::create(['poli_id' => $poli->id, 'hari' => 'RABU', 'jam_buka' => '08:00', 'jam_tutup' => '14:00']);

        $hari = $this->actingAs($admin)->get(route('admin.jam'))
            ->viewData('daftarPoli')
            ->first()
            ->jamOperasional
            ->pluck('hari')
            ->all();

        $this->assertSame(['SENIN', 'RABU', 'SABTU'], $hari, 'Jadwal mengikuti urutan hari dalam seminggu.');
    }

    public function test_halaman_daftar_menampilkan_tautan_urut(): void
    {
        $pasien = $this->buatPasien();
        $petugas = $this->buatPetugas();
        $admin = $this->buatAdmin();
        $poli = $this->buatPoli();
        Antrean::factory()->create(['poli_id' => $poli->id, 'user_id' => $pasien->id]);

        $this->actingAs($petugas)->get(route('petugas.antrean'))
            ->assertOk()
            ->assertSee('urutan', false);

        $this->actingAs($pasien)->get(route('pasien.riwayat'))
            ->assertOk()
            ->assertSee('urutkan=nomor', false);

        $this->actingAs($admin)->get(route('admin.poli'))
            ->assertOk()
            ->assertSee('urutkan=kode', false);
    }
}