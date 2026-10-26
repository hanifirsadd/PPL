<?php

namespace Tests\Unit;

use App\Enums\StatusAntrean;
use PHPUnit\Framework\TestCase;

/**
 * Aturan transisi status antrean (class diagram proposal):
 * MENUNGGU → DIPANGGIL → DILAYANI → SELESAI, dengan cabang
 * DIPANGGIL → DILEWATI → DIPANGGIL.
 */
class StatusAntreanTest extends TestCase
{
    public function test_enam_nilai_status_terdaftar(): void
    {
        $this->assertSame(
            ['MENUNGGU', 'DIPANGGIL', 'DILAYANI', 'SELESAI', 'DILEWATI'],
            array_column(StatusAntrean::cases(), 'value'),
        );
    }

    public function test_alur_normal_menunggu_ke_selesai(): void
    {
        $this->assertTrue(StatusAntrean::MENUNGGU->bolehKe(StatusAntrean::DIPANGGIL));
        $this->assertTrue(StatusAntrean::DIPANGGIL->bolehKe(StatusAntrean::DILAYANI));
        $this->assertTrue(StatusAntrean::DILAYANI->bolehKe(StatusAntrean::SELESAI));
    }

    public function test_cabang_dilewati_dan_panggil_ulang(): void
    {
        $this->assertTrue(StatusAntrean::DIPANGGIL->bolehKe(StatusAntrean::DILEWATI));
        $this->assertTrue(StatusAntrean::DILEWATI->bolehKe(StatusAntrean::DIPANGGIL));
    }

    public function test_lompat_status_ditolak(): void
    {
        $this->assertFalse(StatusAntrean::MENUNGGU->bolehKe(StatusAntrean::DILAYANI));
        $this->assertFalse(StatusAntrean::MENUNGGU->bolehKe(StatusAntrean::SELESAI));
        $this->assertFalse(StatusAntrean::DIPANGGIL->bolehKe(StatusAntrean::MENUNGGU));
        $this->assertFalse(StatusAntrean::SELESAI->bolehKe(StatusAntrean::DIPANGGIL));
    }

    public function test_status_aktif_hanya_tiga(): void
    {
        $this->assertSame(
            ['MENUNGGU', 'DIPANGGIL', 'DILAYANI'],
            array_map(fn (StatusAntrean $s) => $s->value, StatusAntrean::aktif()),
        );
    }

    public function test_label_bahasa_indonesia(): void
    {
        $this->assertSame('Menunggu', StatusAntrean::MENUNGGU->label());
        $this->assertSame('Dilewati', StatusAntrean::DILEWATI->label());
    }
}