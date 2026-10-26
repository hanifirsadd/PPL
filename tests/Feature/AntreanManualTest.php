<?php

namespace Tests\Feature;

use App\Enums\StatusAntrean;
use App\Enums\TipeAntrean;
use App\Models\Antrean;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * US-05 — Input antrean manual oleh petugas untuk pasien yang datang
 * langsung (walk-in). Sesuai ERD: User_id boleh NULL dan identitas
 * dicatat pada Nama_pasien_manual, sedangkan petugas pencatat
 * disimpan pada atribut Dibuat_oleh.
 */
class AntreanManualTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    public function test_petugas_mencatat_antrean_walk_in_tanpa_akun(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        $response = $this->actingAs($petugas)->post(route('petugas.manual.simpan'), [
            'id_poli' => $poli->id,
            'nama' => 'Warga Tanpa Akun',
            'email' => null,
        ]);

        $response->assertRedirect(route('petugas.antrean', ['poli' => $poli->id]))
            ->assertSessionHas('sukses', 'Antrean manual PU-0001 berhasil dibuat.');

        $antrean = Antrean::sole();
        $this->assertNull($antrean->user_id);
        $this->assertSame('Warga Tanpa Akun', $antrean->nama_pasien_manual);
        $this->assertSame($petugas->id, $antrean->dibuat_oleh, 'Petugas pencatat harus tercatat (ERD: Dibuat_oleh).');
        $this->assertSame(TipeAntrean::MANUAL, $antrean->tipe);
        $this->assertSame(StatusAntrean::MENUNGGU, $antrean->status);
    }

    public function test_antrean_manual_berbagi_urutan_dengan_antrean_online(): void
    {
        $poli = $this->buatPoli();
        $petugas = $this->buatPetugas();

        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);
        $this->actingAs($petugas)->post(route('petugas.manual.simpan'), ['id_poli' => $poli->id, 'nama' => 'Ani']);
        $this->actingAs($this->buatPasien())->post(route('pasien.ambil'), ['id_poli' => $poli->id]);

        $this->assertSame(
            ['PU-0001', 'PU-0002', 'PU-0003'],
            Antrean::orderBy('nomor_antrean')->pluck('nomor_antrean')->all(),
        );
    }

    public function test_antrean_manual_dengan_email_terdaftar_terhubung_ke_akun(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien(['email' => 'terdaftar@contoh.test', 'nama' => 'Pasien Terdaftar']);

        $this->actingAs($petugas)->post(route('petugas.manual.simpan'), [
            'id_poli' => $poli->id,
            'nama' => null,
            'email' => 'terdaftar@contoh.test',
        ]);

        $antrean = Antrean::sole();
        $this->assertSame($pasien->id, $antrean->user_id);
        $this->assertNull($antrean->nama_pasien_manual);
    }

    public function test_email_yang_tidak_terdaftar_ditolak(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        $this->actingAs($petugas)
            ->post(route('petugas.manual.simpan'), [
                'id_poli' => $poli->id,
                'nama' => 'Pasien',
                'email' => 'entah@contoh.test',
            ])
            ->assertSessionHasErrors('antrean');

        $this->assertDatabaseCount('antrean', 0);
    }

    public function test_nama_wajib_bila_tanpa_email(): void
    {
        $petugas = $this->buatPetugas();
        $poli = $this->buatPoli();

        $this->actingAs($petugas)
            ->post(route('petugas.manual.simpan'), ['id_poli' => $poli->id, 'nama' => '   ', 'email' => null])
            ->assertSessionHasErrors('antrean');

        $this->assertDatabaseCount('antrean', 0);
    }

    /** KNF-01 tetap berlaku untuk pasien terdaftar yang diinput manual. */
    public function test_pasien_terdaftar_tidak_boleh_punya_dua_antrean_aktif(): void
    {
        $poli = $this->buatPoli();
        $petugas = $this->buatPetugas();
        $pasien = $this->buatPasien(['email' => 'kembar@contoh.test']);

        $this->actingAs($petugas)->post(route('petugas.manual.simpan'), [
            'id_poli' => $poli->id,
            'email' => 'kembar@contoh.test',
        ]);

        $this->actingAs($petugas)
            ->post(route('petugas.manual.simpan'), ['id_poli' => $poli->id, 'email' => 'kembar@contoh.test'])
            ->assertSessionHasErrors('antrean');

        $this->assertSame(1, Antrean::count());
    }

    public function test_antrean_manual_ikut_memakai_kuota(): void
    {
        $poli = $this->buatPoli();
        $petugas = $this->buatPetugas();
        $this->buatKuota($poli, 1);

        $this->actingAs($petugas)->post(route('petugas.manual.simpan'), ['id_poli' => $poli->id, 'nama' => 'Ani']);

        $this->actingAs($petugas)
            ->post(route('petugas.manual.simpan'), ['id_poli' => $poli->id, 'nama' => 'Budi'])
            ->assertSessionHasErrors('antrean');

        $this->assertSame(1, Antrean::count());
    }

    public function test_halaman_input_manual_menampilkan_hanya_poli_aktif(): void
    {
        $this->buatPoli(['nama_poli' => 'Poli Umum']);
        $this->buatPoli(['nama_poli' => 'Poli KIA', 'status_aktif' => false]);

        $this->actingAs($this->buatPetugas())
            ->get(route('petugas.manual'))
            ->assertOk()
            ->assertSee('Poli Umum')
            ->assertDontSee('Poli KIA');
    }

    public function test_pasien_tidak_boleh_input_antrean_manual(): void
    {
        $this->actingAs($this->buatPasien())->get(route('petugas.manual'))->assertForbidden();
    }
}