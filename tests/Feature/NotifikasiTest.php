<?php

namespace Tests\Feature;

use App\Models\Antrean;
use App\Models\Notifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * US-08 — notifikasi otomatis saat tersisa 3 antrean sebelum giliran pasien
 *         (dengan jaminan tidak dikirim berulang).
 * US-09 — notifikasi saat nomor antrean resmi dipanggil.
 *
 * Keduanya ditangani NotifikasiService yang dipanggil AntreanService.
 */
class NotifikasiTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    /** US-09 — pemanggilan membuat notifikasi DIPANGGIL. */
    public function test_notifikasi_dikirim_saat_nomor_dipanggil(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien();

        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $antrean = Antrean::sole();

        $this->actingAs($petugas)->post(route('petugas.panggil'), ['id_poli' => $poli->id]);

        $notifikasi = Notifikasi::where('antrean_id', $antrean->id)
            ->where('jenis', Notifikasi::DIPANGGIL)
            ->firstOrFail();
        $this->assertSame(Notifikasi::DIPANGGIL, $notifikasi->jenis);
        $this->assertSame($pasien->id, $notifikasi->user_id);
        $this->assertStringContainsString($antrean->nomor_antrean, $notifikasi->pesan);
        $this->assertNull($notifikasi->dibaca_pada);
    }

    public function test_notifikasi_tidak_diteruskan_ke_pasien_walk_in_tanpa_akun(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        $this->actingAs($petugas)->post(route('petugas.manual.simpan'), ['id_poli' => $poli->id, 'nama' => 'Walk-in']);
        $this->actingAs($petugas)->post(route('petugas.panggil'), ['id_poli' => $poli->id]);

        $this->assertDatabaseCount('notifikasi', 0);
    }

    /** US-08 — reminder muncul ketika antrean di depan mencapai 3. */
    public function test_reminder_dikirim_saat_tersisa_tiga_antrean(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        // Lima pasien berantrean di poli yang sama (sisa di depan berturut-turut 0,1,2,3,4).
        $antreans = collect(range(1, 5))->map(function (int $ke) use ($poli) {
            $pasien = $this->buatPasien();
            $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

            return Antrean::where('nomor_antrean', sprintf('%s-%04d', $poli->kode_poli, $ke))->firstOrFail();
        });

        // Pengingat dikirim untuk sisa 1..3 (PU-0002, PU-0003, PU-0004).
        // PU-0001 (sisa 0) adalah giliran berikutnya dan PU-0005 masih 4 antrean.
        $penerima = Notifikasi::where('jenis', Notifikasi::REMINDER_TIGA)
            ->pluck('antrean_id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$antreans[1]->id, $antreans[2]->id, $antreans[3]->id],
            $penerima,
        );
    }

    public function test_reminder_tidak_dikirim_untuk_pasien_yang_tepat_gilirannya(): void
    {
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien();

        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $antrean = Antrean::sole();

        $this->assertDatabaseMissing('notifikasi', ['antrean_id' => $antrean->id, 'jenis' => Notifikasi::REMINDER_TIGA]);
    }

    public function test_reminder_tidak_dikirim_berulang_untuk_antrean_yang_sama(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        // Tiga pasien mendahului, sehingga pasien keempat berposisi sisa 3.
        foreach (range(1, 3) as $ignored) {
            $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        }

        $pasien = $this->buatPasien();
        $this->actingAs($pasien)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $antrean = Antrean::where('nomor_antrean', 'PU-0004')->firstOrFail();

        $this->assertSame(
            1,
            Notifikasi::where('antrean_id', $antrean->id)->where('jenis', Notifikasi::REMINDER_TIGA)->count(),
        );

        // Beberapa kali dipanggil ulang dengan status tetap → tidak boleh duplikat.
        $this->actingAs($petugas)->post(route('petugas.panggil'), ['id_poli' => $poli->id]);
        $this->actingAs($petugas)->post(route('petugas.lewati', $antrean->id));
        $this->actingAs($petugas)->post(route('petugas.panggil-ulang', $antrean->id));

        $this->assertSame(
            1,
            Notifikasi::where('antrean_id', $antrean->id)->where('jenis', Notifikasi::REMINDER_TIGA)->count(),
            'Reminder tidak boleh dikirim berulang untuk antrean yang sama (US-08).',
        );
    }

    public function test_reminder_ikut_terkirim_setelah_antrean_selesai(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        $pasienAwal = $this->buatPasien();
        $this->actingAs($pasienAwal)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $pertama = Antrean::sole();

        // Tambahkan empat pasien lagi → PU-0005 masih 4 antrean di depan (belum diberi tahu).
        foreach (range(1, 4) as $ignored) {
            $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        }

        $terakhir = Antrean::where('nomor_antrean', 'PU-0005')->firstOrFail();
        $this->assertDatabaseMissing('notifikasi', [
            'antrean_id' => $terakhir->id,
            'jenis' => Notifikasi::REMINDER_TIGA,
        ]);

        // PU-0001 selesai dilayani → antrean di depan PU-0005 berkurang menjadi 3.
        $this->actingAs($petugas)->post(route('petugas.panggil'), ['id_poli' => $poli->id]);
        $this->actingAs($petugas)->post(route('petugas.mulai', $pertama->id));
        $this->actingAs($petugas)->post(route('petugas.selesai', $pertama->id));

        $this->assertDatabaseHas('notifikasi', [
            'antrean_id' => $terakhir->id,
            'jenis' => Notifikasi::REMINDER_TIGA,
        ]);
    }

    public function test_pasien_menandai_notifikasi_dibaca(): void
    {
        $pasien = $this->buatPasien();
        $notifikasi = Notifikasi::create([
            'antrean_id' => Antrean::factory()->create(['user_id' => $pasien->id])->id,
            'user_id' => $pasien->id,
            'jenis' => Notifikasi::DIPANGGIL,
            'pesan' => 'Nomor antrean dipanggil',
        ]);

        $this->actingAs($pasien)->post(route('pasien.notifikasi.baca', $notifikasi->id));

        $this->assertNotNull($notifikasi->fresh()->dibaca_pada);
    }

    public function test_pasien_tidak_bisa_menandai_notifikasi_milik_orang_lain(): void
    {
        $pemilik = $this->buatPasien();
        $pasien = $this->buatPasien();

        $notifikasi = Notifikasi::create([
            'antrean_id' => Antrean::factory()->create(['user_id' => $pemilik->id])->id,
            'user_id' => $pemilik->id,
            'jenis' => Notifikasi::DIPANGGIL,
            'pesan' => 'Milik orang lain',
        ]);

        $this->actingAs($pasien)->post(route('pasien.notifikasi.baca', $notifikasi->id));

        $this->assertNull($notifikasi->fresh()->dibaca_pada);
    }

    public function test_halaman_notifikasi_menampilkan_daftar_pasien(): void
    {
        $pasien = $this->buatPasien();
        $antrean = Antrean::factory()->create(['user_id' => $pasien->id]);

        Notifikasi::create([
            'antrean_id' => $antrean->id,
            'user_id' => $pasien->id,
            'jenis' => Notifikasi::DIPANGGIL,
            'pesan' => 'Nomor antrean Anda dipanggil.',
        ]);

        $this->actingAs($pasien)
            ->get(route('pasien.notifikasi'))
            ->assertOk()
            ->assertSee('Nomor antrean Anda dipanggil.')
            ->assertSee('Panggilan');
    }

    public function test_ambang_reminder_bisa_diatur_lewat_konfigurasi(): void
    {
        config(['antrean.reminder_sisa' => 1]);

        $poli = $this->buatPoli();

        // Satu pasien di depan → ambang 1 tetap memicu pengingat.
        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $kedua = $this->buatPasien();
        $this->actingAs($kedua)->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $antreanKedua = Antrean::where('nomor_antrean', 'PU-0002')->firstOrFail();

        $this->assertDatabaseHas('notifikasi', [
            'antrean_id' => $antreanKedua->id,
            'jenis' => Notifikasi::REMINDER_TIGA,
        ]);
    }
}