<?php

namespace App\Exceptions;

use Exception;

/**
 * Pelanggaran aturan bisnis antrean (kuota, duplikat aktif, transisi status).
 * Ditangani di lapisan render Handler sehingga form selalu kembali dengan
 * pesan ramah, bukan layar error 500.
 */
class AturanBisnisException extends Exception
{
}
