<?php

namespace Tests\Feature;

use App\Enums\StatusAntrean;
use App\Models\Antrean;
use App\Models\Poli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * Papan antrean publik untuk tamu: dapat dilihat tanpa login, hanya
 * menampilkan nomor antrean (tanpa identitas pasien), dan tamu tetap tidak
 * dapat mengambil antrean.
 */
class PapanTamuTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    public function test_tamu_dapat_membuka_papan_antrean_tanpa_login(): void
    {
        $this->buatPoli(['nama_poli' => 'Poli Umum', 'kode_poli' => 'PU']);

        $this->get(route('papan'))
            ->assertOk()
            ->assertSee('Papan Antrean')
            ->assertSee('Poli Umum');
    }

    public function test_halaman_masuk_memiliki_tombol_masuk_sebagai_tamu(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk sebagai Tamu')
            ->assertSee(route('papan'), false);
    }

    public function test_papan_menampilkan_nomor_yang_dilayani_dipanggil_dan_antrean_berikutnya(): void
    {
        $poli = $this->buatPoli(['nama_poli' => 'Poli Gigi', 'kode_poli' => 'PGT']);

        Antrean::factory()->dilayani()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PGT-0001']);
        Antrean::factory()->dipanggil()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PGT-0002']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PGT-0003']);
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PGT-0004']);
        Antrean::factory()->selesai()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PGT-0005']);

        $this->get(route('papan'))
            ->assertOk()
            ->assertSee('PGT-0001')
            ->assertSee('PGT-0002')
            ->assertSee('PGT-0003')
            ->assertSee('PGT-0004')
            ->assertSee('2 orang menunggu')
            ->assertDontSee('PGT-0005', 'Antrean yang selesai tidak tampil di papan.');
    }

    /** Identitas pasien tidak boleh bocor di halaman publik. */
    public function test_papan_tidak_menampilkan_nama_pasien(): void
    {
        $poli = $this->buatPoli();
        $pasien = $this->buatPasien(['nama' => 'Rahasia Wong']);

        Antrean::factory()->create([
            'user_id' => $pasien->id,
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
        ]);

        $this->get(route('papan'))->assertOk()->assertDontSee('Rahasia Wong');
    }

    public function test_tamu_tidak_bisa_mengambil_antrean(): void
    {
        $poli = $this->buatPoli();

        // Rute pengambilan antrean tetap memerlukan peran pasien.
        $this->post(route('pasien.ambil'), ['id_poli' => $poli->id])->assertRedirect(route('login'));
        $this->assertDatabaseCount('antrean', 0);
    }

    public function test_fragmen_papan_untuk_penyegaran_otomatis(): void
    {
        $poli = $this->buatPoli();
        Antrean::factory()->create(['poli_id' => $poli->id, 'nomor_antrean' => 'PU-0007']);

        $this->get(route('papan.segarkan'))
            ->assertOk()
            ->assertSee('PU-0007')
            ->assertDontSee('<html', 'Fragmen tidak boleh menyertakan layout penuh.');
    }

    public function test_antrean_besok_tidak_ditampilkan(): void
    {
        $poli = $this->buatPoli();
        Antrean::factory()->create([
            'poli_id' => $poli->id,
            'nomor_antrean' => 'PU-0001',
            'tanggal_antrean' => now()->addDay()->toDateString(),
        ]);

        $this->get(route('papan'))->assertOk()->assertDontSee('PU-0001');
    }

    public function test_poli_nonaktif_tidak_ditampilkan(): void
    {
        $this->buatPoli(['nama_poli' => 'Poli Umum']);
        Poli::factory()->nonaktif()->create(['nama_poli' => 'Poli KIA']);

        $this->get(route('papan'))
            ->assertOk()
            ->assertSee('Poli Umum')
            ->assertDontSee('Poli KIA');
    }

    public function test_pasien_yang_sudah_login_tetap_bisa_membuka_papan(): void
    {
        $this->buatPoli(['nama_poli' => 'Poli Umum']);

        $this->actingAs($this->buatPasien())
            ->get(route('papan'))
            ->assertOk()
            ->assertSee('Poli Umum');
    }
}