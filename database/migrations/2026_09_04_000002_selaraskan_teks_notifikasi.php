<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi nomor antrean menjadi berawalan kode poli (PU-0001, PGT-0002).
 * Teks notifikasi yang sudah tersimpan sebelumnya masih memuat nomor lama
 * ("A-0002"), sehingga pesannya diselaraskan dengan nomor terbaru.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "UPDATE notifikasi n
             JOIN antrean a ON a.id = n.antrean_id
             SET n.pesan = REPLACE(n.pesan, '(A-', CONCAT('(', SUBSTRING_INDEX(a.nomor_antrean, '-', 1), '-'))
             WHERE n.pesan LIKE '%(A-%'"
        );
    }

    public function down(): void
    {
        // Pesan notifikasi bersifat historis; tidak dikembalikan.
    }
};