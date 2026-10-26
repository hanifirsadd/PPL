<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SiapkanDataAntrean;
use Tests\TestCase;

/**
 * Beranda mengarahkan pengunjung sesuai status autentikasi dan perannya
 * (US-02), dan halaman masuk hanya untuk tamu.
 */
class BerandaTest extends TestCase
{
    use RefreshDatabase, SiapkanDataAntrean;

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_halaman_masuk_tampil_untuk_tamu(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk')
            ->assertSee('Daftar sebagai pasien');
    }

    public function test_tamu_yang_tahu_url_tidak_bisa_membuka_halaman_masuk_lagi(): void
    {
        $this->actingAs($this->buatPasien())->get(route('login'))->assertRedirect(route('pasien.poli'));
    }

    public function test_beranda_menurutkan_peran(): void
    {
        $this->actingAs($this->buatPasien())->get('/')->assertRedirect(route('pasien.poli'));
        $this->actingAs($this->buatPetugas())->get('/')->assertRedirect(route('petugas.antrean'));
        $this->actingAs($this->buatAdmin())->get('/')->assertRedirect(route('admin.poli'));
    }

    public function test_nama_dan_peran_tampil_pada_navigation(): void
    {
        $petugas = User::factory()->petugas()->create(['nama' => 'Petugas Satu']);

        $this->actingAs($petugas)
            ->get(route('petugas.antrean'))
            ->assertOk()
            ->assertSee('Petugas Satu')
            ->assertSee('PETUGAS')
            ->assertSee('Daftar Antrean')
            ->assertDontSee('Kelola Poli');
    }

    public function test_admin_melihat_link_kelola_data(): void
    {
        $this->actingAs($this->buatAdmin())
            ->get(route('admin.poli'))
            ->assertSee('Jam Operasional')
            ->assertSee('Kuota');
    }
}