<?php

namespace App\Enums;

/**
 * Class diagram proposal: "StatusAntrean digunakan sebagai enumerasi untuk
 * membatasi status antrean ke dalam lima nilai, yaitu MENUNGGU, DIPANGGIL,
 * DILAYANI, SELESAI, dan DILEWATI."
 *
 * Peralihan status normal: MENUNGGU → DIPANGGIL → DILAYANI → SELESAI.
 * DIPANGGIL → DILEWATI (pasien tidak hadir) dan DILEWATI → DIPANGGIL
 * (panggil ulang). Seluruh aturan ini ditempatkan di domain agar mudah
 * diuji otomatis (KNF-03).
 */
enum StatusAntrean: string
{
    case MENUNGGU = 'MENUNGGU';
    case DIPANGGIL = 'DIPANGGIL';
    case DILAYANI = 'DILAYANI';
    case SELESAI = 'SELESAI';
    case DILEWATI = 'DILEWATI';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU => 'Menunggu',
            self::DIPANGGIL => 'Dipanggil',
            self::DILAYANI => 'Dilayani',
            self::SELESAI => 'Selesai',
            self::DILEWATI => 'Dilewati',
        };
    }

    /**
     * Status yang masih menghitung urutan aktif (untuk posisi & KNF-01).
     *
     * @return list<self>
     */
    public static function aktif(): array
    {
        return [self::MENUNGGU, self::DIPANGGIL, self::DILAYANI];
    }

    /**
     * Daftar tujuan yang sah dari status ini (state machine antrean).
     *
     * @return list<self>
     */
    public function transisiDiizinkan(): array
    {
        return match ($this) {
            self::MENUNGGU => [self::DIPANGGIL],
            self::DIPANGGIL => [self::DILAYANI, self::DILEWATI],
            self::DILAYANI => [self::SELESAI],
            self::DILEWATI => [self::DIPANGGIL],
            self::SELESAI => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiDiizinkan(), true);
    }
}
