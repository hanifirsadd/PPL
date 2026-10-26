<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD proposal: entitas ANTREAN — inti sistem.
 *
 * Satu tabel menampung antrean ONLINE dan MANUAL (dibedakan kolom `tipe`),
 * sesuai usulan solusi: "antrean online dan manual masuk ke satu daftar".
 *
 * Menyesuaikan ERD: user_id BOLEH NULL untuk pasien walk-in tanpa akun
 * (identitas dicatat di nama_pasien_manual); petugas pencatat = dibuat_oleh.
 *
 * KNF-01 ditegakkan di level database memakai generated column + unique index
 * (MySQL/MariaDB tidak punya partial unique index ala PostgreSQL):
 *   - kunci_aktif   → satu antrean AKTIF per pasien per poli per hari
 *   - kunci_dilayani → satu pasien DILAYANI per poli per hari
 * Nilai NULL (tidak memenuhi syarat) tidak bentrok karena index unik MySQL
 * mengizinkan banyak NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antrean', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_antrean', 12);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('nama_pasien_manual', 120)->nullable();
            $table->foreignId('poli_id')->constrained('polis')->restrictOnDelete();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_antrean');
            $table->string('tipe', 8);
            $table->string('status', 16)->default('MENUNGGU');
            $table->dateTime('waktu_dipanggil')->nullable();
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->timestamps();

            // nomor antrean unik per poli per hari — ONLINE & MANUAL berbagi urutan
            $table->unique(['poli_id', 'tanggal_antrean', 'nomor_antrean'], 'antrean_nomor_unik');
        });

        // index jalur utama: daftar harian per poli + posisi antrean
        DB::statement('CREATE INDEX antrean_poli_tanggal_status_idx ON antrean (poli_id, tanggal_antrean, status)');
        DB::statement('CREATE INDEX antrean_user_idx ON antrean (user_id)');

        // enumerasi sesuai class diagram (StatusAntrean) + tipe antrean
        DB::statement("ALTER TABLE antrean MODIFY tipe ENUM('ONLINE','MANUAL') NOT NULL");
        DB::statement("ALTER TABLE antrean MODIFY status ENUM('MENUNGGU','DIPANGGIL','DILAYANI','SELESAI','DILEWATI') NOT NULL DEFAULT 'MENUNGGU'");

        // identitas wajib ada: akun pasien ATAU nama manual
        DB::statement('ALTER TABLE antrean ADD CONSTRAINT antrean_identitas_ada CHECK (user_id IS NOT NULL OR nama_pasien_manual IS NOT NULL)');

        // konsistensi stempel waktu terhadap status (integritas aturan bisnis)
        DB::statement("ALTER TABLE antrean ADD CONSTRAINT antrean_waktu_dipanggil_ada CHECK (status NOT IN ('DIPANGGIL','DILAYANI','SELESAI') OR waktu_dipanggil IS NOT NULL)");
        DB::statement("ALTER TABLE antrean ADD CONSTRAINT antrean_waktu_mulai_ada CHECK (status NOT IN ('DILAYANI','SELESAI') OR waktu_mulai IS NOT NULL)");
        DB::statement("ALTER TABLE antrean ADD CONSTRAINT antrean_waktu_selesai_ada CHECK (status <> 'SELESAI' OR waktu_selesai IS NOT NULL)");

        // KNF-01: satu antrean aktif (MENUNGGU/DIPANGGIL/DILAYANI) per pasien per poli per hari
        DB::statement(
            "ALTER TABLE antrean
             ADD COLUMN kunci_aktif VARCHAR(190) GENERATED ALWAYS AS (
                 CASE WHEN status IN ('MENUNGGU','DIPANGGIL','DILAYANI') AND user_id IS NOT NULL
                      THEN CONCAT(user_id, '|', poli_id, '|', tanggal_antrean)
                 END
             ) STORED,
             ADD UNIQUE KEY antrean_satu_aktif_per_pasien (kunci_aktif)"
        );

        // satu pasien sedang DILAYANI per poli per hari (tutup celah dua petugas menekan "Mulai")
        DB::statement(
            "ALTER TABLE antrean
             ADD COLUMN kunci_dilayani VARCHAR(190) GENERATED ALWAYS AS (
                 CASE WHEN status = 'DILAYANI'
                      THEN CONCAT(poli_id, '|', tanggal_antrean)
                 END
             ) STORED,
             ADD UNIQUE KEY antrean_satu_dilayani_per_poli (kunci_dilayani)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('antrean');
    }
};
