<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Utilitas pengurutan daftar (sorting) yang dipakai seragam di semua halaman
 * daftar: pasien (poli, riwayat, notifikasi), petugas (antrean), dan admin
 * (poli, jam operasional, kuota).
 *
 * Cara pakai:
 *   1. Tentukan daftar kolom yang boleh diurutkan (kunci => kolom database).
 *   2. Terapkan pada query lewat Urutan::terapkan().
 *   3. Di tabel, cetak tautan urut lewat partial `partials.urut`.
 *
 * Parameter URL: ?urutkan=<kunci>&arah=asc|desc — filter lain tetap terbawa
 * karena tautan disusun dari URL saat ini.
 */
final class Urutan
{
    /** Arah pengurutan dari request; asc bila tidak diisi. */
    public static function arah(Request $request, string $bawaan = 'asc'): string
    {
        $arah = strtolower((string) $request->query('arah', ''));

        return in_array($arah, ['asc', 'desc'], true) ? $arah : $bawaan;
    }

    /** Kunci kolom yang sedang aktif (selalu salah satu dari $pilihan). */
    public static function kunci(Request $request, array $pilihan, string $bawaan): string
    {
        $diminta = (string) $request->query('urutkan', '');

        return array_key_exists($diminta, $pilihan) ? $diminta : $bawaan;
    }

    /** Terapkan pengurutan pada query; kunci tak dikenal diabaikan. */
    public static function terapkan(Builder $query, Request $request, array $pilihan, string $bawaan, string $arahBawaan = 'asc'): Builder
    {
        $kunci = self::kunci($request, $pilihan, $bawaan);
        $arah = self::arah($request, $arahBawaan);

        return $query->orderBy($pilihan[$kunci], $arah);
    }

    /** True bila kolom ini sedang menjadi kunci pengurutan aktif. */
    public static function aktif(Request $request, array $pilihan, string $bawaan, string $kolom): bool
    {
        return self::kunci($request, $pilihan, $bawaan) === $kolom;
    }

    /** Arah berikutnya ketika tautan kolom tersebut diklik (toggle). */
    public static function arahBerikutnya(Request $request, array $pilihan, string $bawaan, string $kolom): string
    {
        if (! self::aktif($request, $pilihan, $bawaan, $kolom)) {
            return 'asc';
        }

        return self::arah($request) === 'asc' ? 'desc' : 'asc';
    }

    /** Susun URL pengurutan tanpa kehilangan filter yang sedang aktif. */
    public static function url(Request $request, string $kolom, string $arah): string
    {
        return $request->fullUrlWithQuery(['urutkan' => $kolom, 'arah' => $arah]);
    }

    /**
     * Urutan status antrean secara logis (bukan alfabetis):
     * menunggu → dipanggil → dilayani → dilewati → selesai.
     */
    public static function urutanStatus(string $arah = 'asc'): string
    {
        $daftar = "FIELD(status, 'MENUNGGU', 'DIPANGGIL', 'DILAYANI', 'DILEWATI', 'SELESAI')";

        return $arah === 'desc' ? $daftar . ' DESC' : $daftar;
    }
}