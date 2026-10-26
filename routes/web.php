<?php

use App\Http\Controllers\AntreanPasienController;
use App\Http\Controllers\AntreanPetugasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PoliAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Pembagian jalur mengikuti tiga peran pada proposal (US-02): Pasien,
| Petugas, dan Admin. Otorisasi memakai middleware `auth` + `peran:...`
| (KNF-02) sebelum request mencapai controller.
|
*/

// Beranda: sesuai status autentikasi
Route::get('/', function () {
    $pengguna = auth()->user();

    if ($pengguna === null) {
        return redirect()->route('login');
    }

    return match (true) {
        $pengguna->isAdmin() => redirect()->route('admin.poli'),
        $pengguna->isPetugas() => redirect()->route('petugas.antrean'),
        default => redirect()->route('pasien.poli'),
    };
})->name('beranda');

// Papan antrean publik — untuk tamu, tanpa login (hanya melihat).
Route::get('/papan', [AuthController::class, 'papan'])->name('papan');
Route::get('/papan/segarkan', [AuthController::class, 'papanSegarkan'])->name('papan.segarkan');

/*
|--------------------------------------------------------------------------
| Autentikasi (US-01 & US-02)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'tampilkanLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->name('login.proses');

    Route::get('/daftar', [AuthController::class, 'tampilkanDaftar'])->name('register');
    Route::post('/daftar', [AuthController::class, 'daftar'])->name('register.proses');
});

Route::post('/keluar', [AuthController::class, 'keluar'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Pasien (US-03, US-04, US-06, US-07, US-08, US-09)
|--------------------------------------------------------------------------
*/
Route::prefix('pasien')->name('pasien.')->middleware(['auth', 'peran:PASIEN'])->group(function () {
    Route::get('/poli', [AntreanPasienController::class, 'pilihPoli'])->name('poli');
    Route::post('/antrean', [AntreanPasienController::class, 'ambil'])->name('ambil');

    Route::get('/status', [AntreanPasienController::class, 'status'])->name('status');
    // Fragmen status untuk penyegaran otomatis (AJAX).
    Route::get('/status/segarkan', [AntreanPasienController::class, 'fragmenStatus'])->name('status.segarkan');
    Route::get('/riwayat', [AntreanPasienController::class, 'riwayat'])->name('riwayat');

    Route::get('/notifikasi', [AntreanPasienController::class, 'notifikasi'])->name('notifikasi');
    Route::post('/notifikasi/baca-semua', [AntreanPasienController::class, 'tandaiSemuaDibaca'])->name('notifikasi.baca-semua');
    Route::post('/notifikasi/{id}/baca', [AntreanPasienController::class, 'tandaiDibaca'])->name('notifikasi.baca');
});

/*
|--------------------------------------------------------------------------
| Petugas (US-05, US-10 s/d US-15) — Admin ikut boleh mengawasi
|--------------------------------------------------------------------------
*/
Route::prefix('petugas')->name('petugas.')->middleware(['auth', 'peran:PETUGAS,ADMIN'])->group(function () {
    Route::get('/antrean', [AntreanPetugasController::class, 'daftar'])->name('antrean');
    // Fragmen tabel untuk penyegaran otomatis (AJAX), mengikuti filter aktif.
    Route::get('/antrean/segarkan', [AntreanPetugasController::class, 'fragmenDaftar'])->name('antrean.fragmen');

    Route::get('/antrean/manual', [AntreanPetugasController::class, 'formManual'])->name('manual');
    Route::post('/antrean/manual', [AntreanPetugasController::class, 'simpanManual'])->name('manual.simpan');

    Route::post('/antrean/panggil', [AntreanPetugasController::class, 'panggil'])->name('panggil');
    Route::post('/antrean/{id}/panggil', [AntreanPetugasController::class, 'panggilSpesifik'])->name('panggil.spesifik');
    Route::post('/antrean/{id}/mulai', [AntreanPetugasController::class, 'mulai'])->name('mulai');
    Route::post('/antrean/{id}/selesai', [AntreanPetugasController::class, 'selesai'])->name('selesai');
    Route::post('/antrean/{id}/lewati', [AntreanPetugasController::class, 'lewati'])->name('lewati');
    Route::post('/antrean/{id}/panggil-ulang', [AntreanPetugasController::class, 'panggilUlang'])->name('panggil-ulang');
});

/*
|--------------------------------------------------------------------------
| Admin (US-16, US-17, US-18)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'peran:ADMIN'])->group(function () {
    // US-16 — data poli
    Route::get('/poli', [PoliAdminController::class, 'index'])->name('poli');
    Route::post('/poli', [PoliAdminController::class, 'simpan'])->name('poli.simpan');
    Route::get('/poli/{id}/ubah', [PoliAdminController::class, 'ubah'])->name('poli.ubah');
    Route::put('/poli/{id}', [PoliAdminController::class, 'perbarui'])->name('poli.perbarui');
    Route::delete('/poli/{id}', [PoliAdminController::class, 'hapus'])->name('poli.hapus');

    // US-17 — jam operasional
    Route::get('/jam', [PoliAdminController::class, 'jam'])->name('jam');
    Route::post('/jam', [PoliAdminController::class, 'simpanJam'])->name('jam.simpan');
    Route::delete('/jam/{id}', [PoliAdminController::class, 'hapusJam'])->name('jam.hapus');

    // US-18 — kuota antrean harian
    Route::get('/kuota', [PoliAdminController::class, 'kuota'])->name('kuota');
    Route::post('/kuota', [PoliAdminController::class, 'simpanKuota'])->name('kuota.simpan');
    Route::delete('/kuota/{id}', [PoliAdminController::class, 'hapusKuota'])->name('kuota.hapus');
});
