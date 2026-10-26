<?php

namespace Tests\Feature;

use App\Models\JamOperasional;
use App\Models\KuotaAntrean;
use App\Models\Poli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * US-16 — admin mengelola data poli,
 * US-17 — admin mengatur jam operasional,
 * US-18 — admin menetapkan kuota antrean harian,
 * serta penjagaan hak akses berbasis peran (KNF-02).
 */
class AdminTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    /* ----------------------------- US-16: Poli ---------------------------- */

    public function test_admin_dapat_menambah_poli(): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.poli.simpan'), [
                'kode_poli' => 'PGT',
                'nama_poli' => 'Poli Gigi dan Mulut',
                'deskripsi' => 'Layanan kesehatan gigi.',
                'status_aktif' => '1',
            ])
            ->assertRedirect(route('admin.poli'))
            ->assertSessionHas('sukses', 'Poli berhasil ditambahkan.');

        $this->assertDatabaseHas('polis', [
            'kode_poli' => 'PGT',
            'nama_poli' => 'Poli Gigi dan Mulut',
        ]);
    }

    public function test_poli_bisa_dinonaktifkan_lewat_checkbox(): void
    {
        $this->actingAs($this->buatAdmin())->post(route('admin.poli.simpan'), [
            'kode_poli' => 'IMN',
            'nama_poli' => 'Poli Imunisasi',
        ]);

        $this->assertFalse((bool) Poli::where('kode_poli', 'IMN')->value('status_aktif'));
    }

    public function test_kode_poli_harus_unik(): void
    {
        $admin = $this->buatAdmin();
        Poli::factory()->create(['kode_poli' => 'PU']);

        $this->actingAs($admin)->post(route('admin.poli.simpan'), [
            'kode_poli' => 'PU',
            'nama_poli' => 'Poli Duplikat',
        ])->assertSessionHasErrors('kode_poli');
    }

    public function test_admin_dapat_memperbarui_poli(): void
    {
        $admin = $this->buatAdmin();
        $poli = Poli::factory()->create(['kode_poli' => 'PU', 'nama_poli' => 'Poli Umum']);

        $this->actingAs($admin)->put(route('admin.poli.perbarui', $poli->id), [
            'kode_poli' => 'PU',
            'nama_poli' => 'Poli Umum & Rujukan',
            'deskripsi' => 'Diperluas.',
        ])->assertRedirect(route('admin.poli'));

        $this->assertSame('Poli Umum & Rujukan', $poli->fresh()->nama_poli);
    }

    public function test_poli_dengan_riwayat_antrean_hanya_bisa_dinonaktifkan(): void
    {
        $admin = $this->buatAdmin();
        $poli = Poli::factory()->create();
        \App\Models\Antrean::factory()->create(['poli_id' => $poli->id]);

        $this->actingAs($admin)
            ->delete(route('admin.poli.hapus', $poli->id))
            ->assertRedirect(route('admin.poli'))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('polis', ['id' => $poli->id, 'status_aktif' => false]);
    }

    public function test_poli_tanpa_antrean_dapat_dihapus(): void
    {
        $admin = $this->buatAdmin();
        $poli = Poli::factory()->create();

        $this->actingAs($admin)->delete(route('admin.poli.hapus', $poli->id));

        $this->assertDatabaseMissing('polis', ['id' => $poli->id]);
    }

    /* ----------------------- US-17: Jam operasional ---------------------- */

    public function test_admin_dapat_menambah_jam_operasional(): void
    {
        $poli = Poli::factory()->create();

        $this->actingAs($this->buatAdmin())->post(route('admin.jam.simpan'), [
            'poli_id' => $poli->id,
            'hari' => 'SENIN',
            'jam_buka' => 8,
            'menit_buka' => 0,
            'jam_tutup' => 14,
            'menit_tutup' => 0,
        ])->assertRedirect(route('admin.jam'))
            ->assertSessionHas('sukses', 'Jam operasional disimpan.');

        $this->assertDatabaseHas('jam_operasional', [
            'poli_id' => $poli->id,
            'hari' => 'SENIN',
            'jam_buka' => '08:00:00',
            'jam_tutup' => '14:00:00',
        ]);
    }

    /** Jam di luar rentang 00–23 ditolak (format 24 jam). */
    public function test_jam_harus_dalam_rentang_0_sampai_23(): void
    {
        $poli = Poli::factory()->create();

        $this->actingAs($this->buatAdmin())->post(route('admin.jam.simpan'), [
            'poli_id' => $poli->id,
            'hari' => 'SENIN',
            'jam_buka' => 25,
            'menit_buka' => 0,
            'jam_tutup' => 14,
            'menit_tutup' => 0,
        ])->assertSessionHasErrors('jam_buka');

        $this->assertDatabaseCount('jam_operasional', 0);
    }

    public function test_jam_tutup_harus_setelah_jam_buka(): void
    {
        $poli = Poli::factory()->create();

        $this->actingAs($this->buatAdmin())->post(route('admin.jam.simpan'), [
            'poli_id' => $poli->id,
            'hari' => 'SELASA',
            'jam_buka' => 14,
            'menit_buka' => 0,
            'jam_tutup' => 8,
            'menit_tutup' => 0,
        ])->assertSessionHasErrors('jam_tutup');

        $this->assertDatabaseCount('jam_operasional', 0);
    }

    public function test_jadwal_hari_yang_sama_diperbarui_bukan_duplikat(): void
    {
        $admin = $this->buatAdmin();
        $poli = Poli::factory()->create();

        $this->actingAs($admin)->post(route('admin.jam.simpan'), [
            'poli_id' => $poli->id, 'hari' => 'RABU',
            'jam_buka' => 8, 'menit_buka' => 0, 'jam_tutup' => 12, 'menit_tutup' => 0,
        ]);
        $this->actingAs($admin)->post(route('admin.jam.simpan'), [
            'poli_id' => $poli->id, 'hari' => 'RABU',
            'jam_buka' => 9, 'menit_buka' => 30, 'jam_tutup' => 13, 'menit_tutup' => 0,
        ]);

        $this->assertSame(1, JamOperasional::where('poli_id', $poli->id)->where('hari', 'RABU')->count());
        $this->assertSame('09:30:00', JamOperasional::where('poli_id', $poli->id)->where('hari', 'RABU')->value('jam_buka'));
    }

    public function test_admin_dapat_menghapus_jam_operasional(): void
    {
        $admin = $this->buatAdmin();
        $poli = $this->buatPoli();
        $jam = JamOperasional::where('poli_id', $poli->id)->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.jam.hapus', $jam->id));

        $this->assertDatabaseMissing('jam_operasional', ['id' => $jam->id]);
    }

    /* -------------------------- US-18: Kuota ------------------------------ */

    public function test_admin_dapat_menetapkan_kuota_harian(): void
    {
        $poli = Poli::factory()->create();

        $this->actingAs($this->buatAdmin())->post(route('admin.kuota.simpan'), [
            'poli_id' => $poli->id,
            'tanggal_berlaku' => now()->toDateString(),
            'jumlah_kuota' => 25,
        ])->assertRedirect(route('admin.kuota'))
            ->assertSessionHas('sukses', 'Kuota antrean harian disimpan.');

        $this->assertDatabaseHas('kuota_antrean', [
            'poli_id' => $poli->id,
            'tanggal_berlaku' => now()->toDateString(),
            'jumlah_kuota' => 25,
        ]);
    }

    public function test_kuota_tidak_boleh_nol_atau_negatif(): void
    {
        $poli = Poli::factory()->create();

        $this->actingAs($this->buatAdmin())->post(route('admin.kuota.simpan'), [
            'poli_id' => $poli->id,
            'tanggal_berlaku' => now()->toDateString(),
            'jumlah_kuota' => 0,
        ])->assertSessionHasErrors('jumlah_kuota');

        $this->assertDatabaseCount('kuota_antrean', 0);
    }

    public function test_kuota_tidak_boleh_untuk_tanggal_lampau(): void
    {
        $poli = Poli::factory()->create();

        $this->actingAs($this->buatAdmin())->post(route('admin.kuota.simpan'), [
            'poli_id' => $poli->id,
            'tanggal_berlaku' => now()->subDay()->toDateString(),
            'jumlah_kuota' => 20,
        ])->assertSessionHasErrors('tanggal_berlaku');
    }

    public function test_kuota_tanggal_sama_diperbarui(): void
    {
        $admin = $this->buatAdmin();
        $poli = Poli::factory()->create();

        $this->actingAs($admin)->post(route('admin.kuota.simpan'), [
            'poli_id' => $poli->id, 'tanggal_berlaku' => now()->toDateString(), 'jumlah_kuota' => 10,
        ]);
        $this->actingAs($admin)->post(route('admin.kuota.simpan'), [
            'poli_id' => $poli->id, 'tanggal_berlaku' => now()->toDateString(), 'jumlah_kuota' => 40,
        ]);

        $this->assertSame(1, KuotaAntrean::where('poli_id', $poli->id)->count());
        $this->assertSame(40, KuotaAntrean::where('poli_id', $poli->id)->value('jumlah_kuota'));
    }

    public function test_admin_dapat_menghapus_kuota(): void
    {
        $admin = $this->buatAdmin();
        $poli = Poli::factory()->create();
        $kuota = $this->buatKuota($poli, 15);

        $this->actingAs($admin)->delete(route('admin.kuota.hapus', $kuota->id));

        $this->assertDatabaseMissing('kuota_antrean', ['id' => $kuota->id]);
    }

    /* --------------------------- Hak akses -------------------------------- */

    public function test_petugas_tidak_bisa_mengakses_halaman_admin(): void
    {
        $this->actingAs($this->buatPetugas())->get(route('admin.kuota'))->assertForbidden();
        $this->actingAs($this->buatPetugas())->post(route('admin.poli.simpan'), [])->assertForbidden();
    }

    public function test_halaman_admin_menampilkan_data(): void
    {
        $poli = Poli::factory()->create(['nama_poli' => 'Poli Umum']);

        $this->actingAs($this->buatAdmin())
            ->get(route('admin.poli'))
            ->assertOk()
            ->assertSee('Poli Umum')
            ->assertSee('Tambah Poli');

        $this->actingAs($this->buatAdmin())
            ->get(route('admin.jam'))
            ->assertOk()
            ->assertSee('Jam Buka');

        $this->actingAs($this->buatAdmin())
            ->get(route('admin.kuota'))
            ->assertOk()
            ->assertSee('Jumlah Maksimal');
    }
}