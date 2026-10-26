<?php

namespace Tests\Browser;

use App\Models\Antrean;
use Tests\DuskTestCase;

/**
 * US-05 dan US-10 s/d US-15 diuji lewat browser: petugas mencatat antrean
 * manual, lalu menjalankan alur panggil → layani → selesai serta cabang
 * lewati → panggil ulang.
 */
class PetugasAntreanDuskTest extends DuskTestCase
{
    private function loginPetugas($browser): void
    {
        $browser->visit('/masuk')
            ->type('input[name="email"]', 'petugas@contoh.test')
            ->type('input[name="password"]', 'rahasia123')
            ->press('Masuk')
            ->waitForText('Daftar Antrean Hari Ini');
    }

    public function test_petugas_mencatat_antrean_manual(): void
    {
        $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPetugas(['email' => 'petugas@contoh.test', 'password' => 'rahasia123']);

        $this->browse(function ($browser) {
            $this->loginPetugas($browser);

            $browser->visit('/petugas/antrean/manual')
                ->waitForText('Input Antrean Manual')
                ->select('select[name="id_poli"]', '1')
                ->type('input[name="nama"]', 'Warga Walk-in')
                ->press('Buat Nomor Antrean')
                ->waitForText('Antrean manual PU-0001 berhasil dibuat.')
                ->assertSee('Warga Walk-in')
                ->assertSee('tanpa akun');
        });

        $this->assertDatabaseHas('antrean', [
            'nama_pasien_manual' => 'Warga Walk-in',
            'tipe' => 'MANUAL',
        ]);
    }

    public function test_alur_panggil_layani_selesai(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPetugas(['email' => 'petugas@contoh.test', 'password' => 'rahasia123']);

        $antrean = Antrean::factory()->create([
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        $this->browse(function ($browser) use ($poli) {
            $this->loginPetugas($browser);

            $browser->visit('/petugas/antrean?poli=' . $poli->id)
                ->waitForText('PU-0001')
                ->press('Panggil Berikutnya (US-11)')
                ->waitForText('Memanggil nomor PU-0001.')
                ->assertSee('Dipanggil');

            $browser->press('Mulai Layani (US-12)')
                ->waitForText('sedang dilayani.')
                ->assertSee('Dilayani');

            $browser->press('Selesai (US-13)')
                ->waitForText('selesai dilayani.')
                ->assertSee('Selesai');
        });

        $this->assertSame('SELESAI', $antrean->fresh()->status->value);
        $this->assertNotNull($antrean->fresh()->waktu_selesai);
    }

    public function test_alur_lewati_dan_panggil_ulang(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPetugas(['email' => 'petugas@contoh.test', 'password' => 'rahasia123']);

        $antrean = Antrean::factory()->create([
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        $this->browse(function ($browser) use ($poli) {
            $this->loginPetugas($browser);

            $browser->visit('/petugas/antrean?poli=' . $poli->id)
                ->waitForText('PU-0001')
                ->press('Panggil Berikutnya (US-11)')
                ->waitForText('Memanggil nomor PU-0001.')
                ->press('Lewati (US-14)')
                ->waitForText('dilewati.')
                ->assertSee('Dilewati')
                ->press('Panggil Ulang (US-15)')
                ->waitForText('dipanggil ulang.')
                ->assertSee('Dipanggil');
        });

        $this->assertSame('DIPANGGIL', $antrean->fresh()->status->value);
    }

    public function test_daftar_bisa_disaring_per_status(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPetugas(['email' => 'petugas@contoh.test', 'password' => 'rahasia123']);

        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        Antrean::factory()->selesai()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);

        $this->browse(function ($browser) use ($poli) {
            $this->loginPetugas($browser);

            $browser->visit('/petugas/antrean?poli=' . $poli->id . '&status=MENUNGGU')
                ->waitForText('PU-0001')
                ->assertSee('PU-0001')
                ->assertDontSee('PU-0002');
        });
    }

    /** Tombol Panggil pada baris tertentu memanggil nomor itu, bukan nomor teratas. */
    public function test_panggil_nomor_tertentu_dari_tombol_baris(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPetugas(['email' => 'petugas@contoh.test', 'password' => 'rahasia123']);

        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0001']);
        $kedua = Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0002']);

        $this->browse(function ($browser) use ($poli) {
            $this->loginPetugas($browser);

            $browser->visit('/petugas/antrean?poli=' . $poli->id)
                ->waitForText('PU-0002')
                ->press('📞 Panggil PU-0002 (US-11)')
                ->waitForText('Memanggil nomor PU-0002.');
        });

        $this->assertSame('DIPANGGIL', $kedua->fresh()->status->value);
    }
}