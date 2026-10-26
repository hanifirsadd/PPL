<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nomor antrean kini memakai awalan kode poli (PU-0001, PGT-0002) agar
 * nomor selalu unik di seluruh puskesmas. Kolom diperlebar karena kode poli
 * dapat terdiri atas sampai 16 karakter, dan nomor lama ("A-0001") diubah
 * menjadi "<kode poli>-0001" supaya formatnya seragam.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE antrean MODIFY nomor_antrean VARCHAR(32) NOT NULL');

        // Konversi nomor lama (A-0001) menjadi PU-0001 berdasarkan polinya.
        DB::statement(
            "UPDATE antrean a
             JOIN polis p ON p.id = a.poli_id
             SET a.nomor_antrean = CONCAT(UPPER(p.kode_poli), '-', SUBSTRING_INDEX(a.nomor_antrean, '-', -1))
             WHERE a.nomor_antrean LIKE 'A-%'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "UPDATE antrean SET nomor_antrean = CONCAT('A-', SUBSTRING_INDEX(nomor_antrean, '-', -1))"
        );

        DB::statement('ALTER TABLE antrean MODIFY nomor_antrean VARCHAR(12) NOT NULL');
    }
};