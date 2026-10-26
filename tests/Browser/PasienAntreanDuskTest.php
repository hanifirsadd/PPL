<?php

namespace Tests\Browser;

use Tests\DuskTestCase;

/**
 * US-03 s/d US-09 diuji lewat browser: pasien melihat daftar poli,
 * mengambil nomor antrean, memantau posisi, dan membaca notifikasi.
 */
class PasienAntreanDuskTest extends DuskTestCase
{
    public function test_pasien_ambil_antrean_dan_memantau_posisi(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $pasien = $this->buatPasien(['email' => 'budi@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) use ($poli) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'budi@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Pilih Poli')
                ->assertSee('Poli Umum')
                ->assertSee('Buka');

            $browser->press('Ambil Antrean')
                ->waitForText('Status Antrean Saya')
                ->assertPathIs('/pasien/status')
                ->assertSee('PU-0001')
                ->assertSee('Menunggu')
                ->assertSee('Antrean di depan Anda');
        });

        $this->assertDatabaseHas('antrean', [
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
            'status' => 'MENUNGGU',
        ]);
    }

    public function test_pasien_tidak_boleh_ambil_antrean_kedua(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPasien(['email' => 'budi@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) use ($poli) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'budi@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Pilih Poli')
                ->press('Ambil Antrean')
                ->waitForText('Status Antrean Saya');

            $browser->visit('/pasien/poli')
                ->waitForText('Anda sudah memiliki nomor antrean aktif')
                ->assertSee('Sudah ambil antrean');
        });

        $this->assertSame(1, \App\Models\Antrean::count());
    }

    public function test_pasien_melihat_status_sesudah_dipanggil(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $pasien = $this->buatPasien(['email' => 'budi@contoh.test', 'password' => 'rahasia123']);

        \App\Models\Antrean::factory()->create([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        $antrean = \App\Models\Antrean::sole();
        app(\App\Services\AntreanService::class)->panggilBerikutnya($poli->id);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'budi@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Status Antrean')
                ->assertSee('PU-0001')
                ->assertSee('Dipanggil');
        });

        $this->assertSame('DIPANGGIL', $antrean->fresh()->status->value);
    }

    public function test_pasien_membaca_notifikasi(): void
    {
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien(['email' => 'budi@contoh.test', 'password' => 'rahasia123']);
        $antrean = \App\Models\Antrean::factory()->create([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        app(\App\Services\NotifikasiService::class)->kirimDipanggil($antrean);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'budi@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Pilih Poli');

            $browser->visit('/pasien/notifikasi')
                ->waitForText('sedang dipanggil')
                ->press('Tandai semua dibaca')
                ->waitForText('Semua notifikasi ditandai sudah dibaca');
        });

        $this->assertNotNull(\App\Models\Notifikasi::sole()->fresh()->dibaca_pada);
    }

    public function test_riwayat_antrean_tampil(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Gigi']);
        $pasien = $this->buatPasien(['email' => 'budi@contoh.test', 'password' => 'rahasia123']);

        \App\Models\Antrean::factory()->selesai()->create([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        $this->browse(function ($browser) {
            $browser->visit('/masuk')
                ->type('input[name="email"]', 'budi@contoh.test')
                ->type('input[name="password"]', 'rahasia123')
                ->press('Masuk')
                ->waitForText('Pilih Poli');

            $browser->visit('/pasien/riwayat')
                ->waitForText('Riwayat Antrean')
                ->assertSee('PU-0001')
                ->assertSee('Poli Gigi')
                ->assertSee('Selesai');
        });
    }
}