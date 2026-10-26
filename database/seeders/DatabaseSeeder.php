<?php

namespace Database\Seeders;

use App\Models\Antrean;
use App\Models\JamOperasional;
use App\Models\KuotaAntrean;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed dasar untuk pengembangan & demo.
     *
     * Akun contoh memakai kata sandi "password123" — GANTI sebelum dipakai
     * pada lingkungan yang nyata.
     */
    public function run(): void
    {
        // ------------------------------------------------ akun (ERD USERS)
        $admin = User::updateOrCreate(
            ['email' => 'admin@puskesmas.test'],
            ['nama' => 'Admin Puskesmas', 'password' => 'password123', 'peran' => User::PERAN_ADMIN],
        );

        $petugas = User::updateOrCreate(
            ['email' => 'petugas@puskesmas.test'],
            ['nama' => 'Petugas Loket', 'password' => 'password123', 'peran' => User::PERAN_PETUGAS],
        );

        $budi = User::updateOrCreate(
            ['email' => 'budi@contoh.test'],
            ['nama' => 'Budi Santoso', 'password' => 'password123', 'peran' => User::PERAN_PASIEN],
        );

        $siti = User::updateOrCreate(
            ['email' => 'siti@contoh.test'],
            ['nama' => 'Siti Aminah', 'password' => 'password123', 'peran' => User::PERAN_PASIEN],
        );

        $andi = User::updateOrCreate(
            ['email' => 'andi@contoh.test'],
            ['nama' => 'Andi Wijaya', 'password' => 'password123', 'peran' => User::PERAN_PASIEN],
        );

        // -------------------------------------------------- poli (ERD POLIS)
        $daftarPoli = collect([
            ['kode_poli' => 'PU', 'nama_poli' => 'Poli Umum', 'deskripsi' => 'Pemeriksaan umum dan rujukan tingkat pertama.'],
            ['kode_poli' => 'PGT', 'nama_poli' => 'Poli Gigi dan Mulut', 'deskripsi' => 'Layanan kesehatan gigi dan mulut.'],
            ['kode_poli' => 'KIA', 'nama_poli' => 'Poli KIA', 'deskripsi' => 'Kesehatan ibu, bayi, dan anak.'],
            ['kode_poli' => 'IMN', 'nama_poli' => 'Poli Imunisasi', 'deskripsi' => 'Imunisasi bayi dan anak sesuai jadwal.'],
        ])->map(fn (array $poli) => Poli::updateOrCreate(
            ['kode_poli' => $poli['kode_poli']],
            $poli + ['status_aktif' => true],
        ));

        // --------------------------------- jam operasional (ERD JAM_OPERASIONAL)
        foreach ($daftarPoli as $poli) {
            foreach (['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT'] as $hari) {
                JamOperasional::updateOrCreate(
                    ['poli_id' => $poli->id, 'hari' => $hari],
                    ['jam_buka' => '08:00:00', 'jam_tutup' => '14:00:00'],
                );
            }

            JamOperasional::updateOrCreate(
                ['poli_id' => $poli->id, 'hari' => 'SABTU'],
                ['jam_buka' => '08:00:00', 'jam_tutup' => '12:00:00'],
            );
        }

        // -------------------------------------- kuota harian (ERD KUOTA_ANTREAN)
        foreach ($daftarPoli as $poli) {
            foreach ([0, 1] as $hariKe) {
                KuotaAntrean::updateOrCreate(
                    [
                        'poli_id' => $poli->id,
                        'tanggal_berlaku' => Carbon::today()->addDays($hariKe)->toDateString(),
                    ],
                    ['jumlah_kuota' => 30],
                );
            }
        }

        // ------------------------- contoh antrean hari ini (hanya bila kosong)
        $poliUmum = $daftarPoli->firstWhere('kode_poli', 'PU');
        $hariIni = Carbon::today()->toDateString();

        if (Antrean::where('tanggal_antrean', $hariIni)->exists()) {
            return;
        }

        $contoh = [
            ['user_id' => $budi->id, 'nama' => null, 'nomor' => 'PU-0001', 'tipe' => 'ONLINE', 'dibuat_oleh' => null],
            ['user_id' => null, 'nama' => 'Warga Walk-in', 'nomor' => 'PU-0002', 'tipe' => 'MANUAL', 'dibuat_oleh' => $petugas->id],
            ['user_id' => $andi->id, 'nama' => null, 'nomor' => 'PU-0003', 'tipe' => 'MANUAL', 'dibuat_oleh' => $petugas->id],
        ];

        foreach ($contoh as $baris) {
            Antrean::updateOrCreate(
                ['poli_id' => $poliUmum->id, 'tanggal_antrean' => $hariIni, 'nomor_antrean' => $baris['nomor']],
                [
                    'user_id' => $baris['user_id'],
                    'nama_pasien_manual' => $baris['nama'],
                    'dibuat_oleh' => $baris['dibuat_oleh'],
                    'tipe' => $baris['tipe'],
                    'status' => 'MENUNGGU',
                ],
            );
        }

        unset($siti, $admin); // dipakai untuk pengujian manual bila diperlukan
    }
}
