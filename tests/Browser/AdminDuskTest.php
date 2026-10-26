<?php

namespace Tests\Browser;

use App\Models\Poli;
use Tests\DuskTestCase;

/**
 * US-16 s/d US-18 diuji lewat browser: admin menambah poli, mengatur jam
 * operasional, dan menetapkan kuota harian.
 */
class AdminDuskTest extends DuskTestCase
{
    public function test_admin_menambah_dan_memperbarui_poli(): void
    {
        $this->buatAdmin(['email' => 'admin@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'admin@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Data Poli');

            $browser->type('input[name="kode_poli"]', 'PGT')
                ->type('input[name="nama_poli"]', 'Poli Gigi dan Mulut')
                ->type('textarea[name="deskripsi"]', 'Layanan kesehatan gigi.')
                ->press('Simpan Poli')
                ->waitForText('Poli berhasil ditambahkan.')
                ->assertSee('Poli Gigi dan Mulut');

            $poli = Poli::where('kode_poli', 'PGT')->firstOrFail();

            $browser->visit('/admin/poli/' . $poli->id . '/ubah')
                ->waitForText('Ubah Poli')
                ->clear('input[name="nama_poli"]')
                ->type('input[name="nama_poli"]', 'Poli Gigi')
                ->press('Simpan Perubahan')
                ->waitForText('Poli berhasil diperbarui.')
                ->assertSee('Poli Gigi');
        });

        $this->assertDatabaseHas('polis', ['kode_poli' => 'PGT', 'nama_poli' => 'Poli Gigi']);
    }

    public function test_admin_menetapkan_jam_operasional(): void
    {
        $poli = Poli::factory()->create(['nama_poli' => 'Poli Umum']);
        $this->buatAdmin(['email' => 'admin@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) use ($poli) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'admin@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Data Poli');

            $browser->visit('/admin/jam')
                ->waitForText('Jam Operasional')
                ->select('select[name="poli_id"]', (string) $poli->id)
                ->select('select[name="hari"]', 'SENIN')
                ->select('select[name="jam_buka"]', '8')
                ->select('select[name="menit_buka"]', '0')
                ->select('select[name="jam_tutup"]', '14')
                ->select('select[name="menit_tutup"]', '0')
                ->press('Simpan Jadwal')
                ->waitForText('Jam operasional disimpan.')
                ->assertSee('08:00');
        });

        $this->assertDatabaseHas('jam_operasional', [
            'poli_id' => $poli->id,
            'hari' => 'SENIN',
            'jam_buka' => '08:00:00',
            'jam_tutup' => '14:00:00',
        ]);
    }

    public function test_admin_menetapkan_kuota_antrean(): void
    {
        $poli = Poli::factory()->create(['nama_poli' => 'Poli Umum']);
        $this->buatAdmin(['email' => 'admin@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) use ($poli) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'admin@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Data Poli');

            $browser->visit('/admin/kuota')
                ->waitForText('Kuota Antrean Harian')
                ->select('select[name="poli_id"]', (string) $poli->id)
                ->type('input[name="jumlah_kuota"]', '25')
                ->press('Simpan Kuota')
                ->waitForText('Kuota antrean harian disimpan.')
                ->assertSee('25 pasien');
        });

        $this->assertDatabaseHas('kuota_antrean', ['poli_id' => $poli->id, 'jumlah_kuota' => 25]);
    }

    public function test_halaman_admin_tidak_bisa_diakses_pasien(): void
    {
        $this->buatPasien(['email' => 'budi@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'budi@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Pilih Poli');

            $browser->visit('/admin/poli')
                ->waitForText('403')
                ->assertSee('403');
        });
    }
}