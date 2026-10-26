<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD proposal: entitas POLIS.
 * Kode_poli unik; Is_active (status_aktif) menentukan poli tampil bagi pasien (US-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polis', function (Blueprint $table) {
            $table->id();
            $table->string('kode_poli', 16)->unique();
            $table->string('nama_poli', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        // Nama poli unik. Kolasi utf8mb4 *_ci sudah bersifat case-insensitive,
        // sehingga index unik biasa menjamin keunikan tanpa index fungsional
        // LOWER() (MariaDB — mesin DB XAMPP — tidak mendukung index fungsional
        // maupun parsial ala PostgreSQL).
        DB::statement('CREATE UNIQUE INDEX polis_nama_unik ON polis (nama_poli)');
    }

    public function down(): void
    {
        Schema::dropIfExists('polis');
    }
};
