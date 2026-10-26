<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\Antrean;
use Tests\DuskTestCase;

/**
 * US-01 & US-02 diuji lewat browser sungguhan (KT-02):
 * pendaftaran pasien, login, pengalahan dashboard sesuai peran, dan penolakan
 * akses antar peran (KNF-02).
 */
class LoginDuskTest extends DuskTestCase
{
    public function test_pasien_dapat_mendaftar_dan_login(): void
    {
        $this->browse(function ($browser) {
            $browser->visit('/daftar')
                ->assertSee('Registrasi Pasien')
                ->type('input[name="nama"]', 'Rina Dewi')
                ->type('input[name="email"]', 'rina@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->type('input[name="password_confirmation"]', 'rahasia123')
                ->press('Buat Akun');

            $browser->waitForText('Pilih Poli')
                ->assertPathIs('/pasien/poli')
                ->assertSee('Rina Dewi')
                ->assertSee('PASIEN');
        });

        $this->assertDatabaseHas('users', ['email' => 'rina@contoh.test', 'peran' => 'PASIEN']);
    }

    public function test_login_gagal_dengan_kata_sandi_salah(): void
    {
        $this->buatPasien(['email' => 'salah@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'salah@contoh.test')
                ->type('input[name="password"]', 'password-keliru')
                ->press('Masuk')
                ->waitForText('Email atau kata sandi salah.')
                ->assertPathIs('/masuk');
        });
    }

    public function test_petugas_diarahkan_ke_daftar_antrean(): void
    {
        $this->buatPetugas(['email' => 'petugas@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'petugas@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk');

            $browser->waitForText('Daftar Antrean Hari Ini')
                ->assertPathIs('/petugas/antrean')
                ->assertSee('PETUGAS');
        });
    }

    public function test_admin_diarahkan_ke_kelola_poli(): void
    {
        $this->buatAdmin(['email' => 'admin@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'admin@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk');

            $browser->waitForText('Data Poli')
                ->assertPathIs('/admin/poli')
                ->assertSee('ADMIN');
        });
    }

    public function test_pasien_tidak_bisa_membuka_halaman_petugas(): void
    {
        $this->buatPasien(['email' => 'budi@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'budi@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Pilih Poli');

            $browser->visit('/petugas/antrean')
                ->waitForText('403')
                ->assertSee('ANDA TIDAK MEMILIKI AKSES');
        });
    }

    /** Tamu dapat melihat papan antrean dari halaman masuk tanpa akun. */
    public function test_tamu_melihat_papan_antrean_tanpa_login(): void
    {
        $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $poli = \App\Models\Poli::where('nama_poli', 'Poli Umum')->firstOrFail();
        Antrean::factory()->dilayani()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->waitForText('Masuk sebagai Tamu')
                ->click('#tombol-tamu')
                ->waitForText('Papan Antrean')
                ->assertPathIs('/papan')
                ->assertSee('Poli Umum')
                ->assertSee('PU-0001')
                ->assertSee('PU-0002');
        });

        $this->assertGuest();
    }
}