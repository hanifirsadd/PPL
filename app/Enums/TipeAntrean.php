<?php

namespace App\Enums;

/**
 * ERD proposal: atribut Tipe pada entitas ANTREAN membedakan antrean
 * pendaftaran online (US-04) dan input manual petugas (US-05) yang
 * berbagi satu daftar.
 */
enum TipeAntrean: string
{
    case ONLINE = 'ONLINE';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'Online',
            self::MANUAL => 'Manual',
        };
    }
}
