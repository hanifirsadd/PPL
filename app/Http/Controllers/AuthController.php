<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AntreanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Class diagram proposal: AuthController bertanggung jawab atas registrasi,
 * login, dan logout pengguna (US-01, US-02).
 */
class AuthController extends Controller
{
    /** US-02 — halaman login. */
    public function tampilkanLogin(): View
    {
        return view('auth.login');
    }

    /** US-02 — autentikasi lalu arahkan ke dashboard sesuai peran. */
    public function login(Request $request): RedirectResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'email',
            'password' => 'kata sandi',
        ]);

        if (! Auth::attempt($kredensial, $request->boolean('ingat'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->tujuanDashboard(Auth::user()));
    }

    /** US-01 — halaman registrasi pasien. */
    public function tampilkanDaftar(): View
    {
        return view('auth.daftar');
    }

    /** US-01 — buat akun pasien setelah validasi data. */
    public function daftar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [], [
            'nama' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
        ]);

        // Registrasi mandiri hanya menghasilkan akun PASIEN (karakteristik
        // pengguna: petugas & admin dibuat oleh admin puskesmas).
        $pengguna = User::create([
            'nama' => $data['nama'],
            'email' => strtolower($data['email']),
            'password' => $data['password'], // casting 'hashed' pada model → bcrypt (KNF-02)
            'peran' => User::PERAN_PASIEN,
        ]);

        Auth::login($pengguna);
        $request->session()->regenerate();

        return redirect()->route('pasien.poli')->with('sukses', 'Akun berhasil dibuat. Silakan ambil nomor antrean.');
    }

    /**
 * Papan antrean publik — dapat dilihat tamu tanpa login (hanya melihat,
 * tidak dapat mengambil antrean). Metode ini dipakai AuthController agar
 * tidak menambah kelas controller baru di luar class diagram proposal.
 */
    public function papan(Request $request, AntreanService $antrean): View
    {
        $data = $antrean->papanAntrean();

        return view('papan', [
            'daftarPapan' => $data,
            'jumlahMenunggu' => collect($data)->sum(fn ($baris) => $baris['menunggu']->count()),
        ]);
    }

    /** Versi fragmen untuk penyegaran otomatis (AJAX) oleh tamu. */
    public function papanSegarkan(AntreanService $antrean): View
    {
        $data = $antrean->papanAntrean();

        return view('papan.partials.isi', [
            'daftarPapan' => $data,
            'jumlahMenunggu' => collect($data)->sum(fn ($baris) => $baris['menunggu']->count()),
        ]);
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('sukses', 'Anda telah keluar.');
    }

    /** US-02 — pengalihan berdasar peran: Pasien, Petugas, atau Admin. */
    private function tujuanDashboard(?User $pengguna): string
    {
        return match (true) {
            $pengguna?->isAdmin() === true => route('admin.poli'),
            $pengguna?->isPetugas() === true => route('petugas.antrean'),
            default => route('pasien.poli'),
        };
    }
}
