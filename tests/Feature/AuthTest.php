<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US-01 (registrasi pasien) & US-02 (login + pengalihan dashboard sesuai peran).
 * KNF-02: kata sandi disimpan sebagai hash.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrasi_membuat_akun_pasien(): void
    {
        $response = $this->post('/daftar', [
            'nama' => 'Dewi Lestari',
            'email' => 'dewi@contoh.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $response->assertRedirect(route('pasien.poli'));

        $pengguna = User::where('email', 'dewi@contoh.test')->firstOrFail();
        $this->assertSame('Dewi Lestari', $pengguna->nama);
        $this->assertSame(User::PERAN_PASIEN, $pengguna->peran);
        $this->assertNotSame('rahasia123', $pengguna->password, 'Password harus disimpan sebagai hash (KNF-02).');
        $this->assertTrue($pengguna->isPasien());
        $this->assertAuthenticatedAs($pengguna);
    }

    public function test_registrasi_menolak_email_duplikat(): void
    {
        User::factory()->pasien()->create(['email' => 'sudah@ada.test']);

        $this->post('/daftar', [
            'nama' => 'Duplikat',
            'email' => 'sudah@ada.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registrasi_menolak_konfirmasi_kata_sandi_beda(): void
    {
        $this->post('/daftar', [
            'nama' => 'Salah Konfirmasi',
            'email' => 'salah@kontoh.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia456',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    /** US-02: login pasien diarahkan ke halaman pilih poli. */
    public function test_login_pasien_ke_halaman_poli(): void
    {
        $pasien = User::factory()->pasien()->create(['password' => 'rahasia123']);

        $this->post('/masuk', ['email' => $pasien->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('pasien.poli'));

        $this->assertAuthenticatedAs($pasien);
    }

    /** US-02: login petugas diarahkan ke daftar antrean. */
    public function test_login_petugas_ke_daftar_antrean(): void
    {
        $petugas = User::factory()->petugas()->create(['password' => 'rahasia123']);

        $this->post('/masuk', ['email' => $petugas->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('petugas.antrean'));
    }

    /** US-02: login admin diarahkan ke kelola poli. */
    public function test_login_admin_ke_kelola_poli(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.poli'));
    }

    public function test_login_gagal_dengan_kata_sandi_salah(): void
    {
        $pasien = User::factory()->pasien()->create(['password' => 'rahasia123']);

        $this->post('/masuk', ['email' => $pasien->email, 'password' => 'salah-sekali'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_halaman_admin_menolak_pasien_dan_petugas(): void
    {
        $pasien = User::factory()->pasien()->create();

        $this->actingAs($pasien)->get(route('admin.poli'))->assertForbidden();
    }

    public function test_halaman_petugas_menolak_pasien(): void
    {
        $pasien = User::factory()->pasien()->create();

        $this->actingAs($pasien)->get(route('petugas.antrean'))->assertForbidden();
    }

    public function test_halaman_pasien_menolak_petugas(): void
    {
        $petugas = User::factory()->petugas()->create();

        $this->actingAs($petugas)->get(route('pasien.poli'))->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('pasien.poli'))->assertRedirect(route('login'));
        $this->get(route('petugas.antrean'))->assertRedirect(route('login'));
        $this->get(route('admin.poli'))->assertRedirect(route('login'));
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $pasien = User::factory()->pasien()->create();

        $this->actingAs($pasien)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}