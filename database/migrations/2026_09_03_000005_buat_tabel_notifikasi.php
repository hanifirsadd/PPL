<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD proposal: entitas NOTIFIKASI (US-08 & US-09).
 *
 * Jenis: REMINDER_3_ANTREAN (tersisa 3 antrean) dan DIPANGGIL (nomor dipanggil).
 * UNIQUE (antrean_id, jenis) menjadikan anti-duplikasi (US-08: "reminder tidak
 * dikirim berulang kali untuk antrean yang sama") sebagai constraint database.
 *
 * Catatan pemetaan ERD: DIBUAT_PADA dipetakan ke kolom bawaan created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('antrean_id')->constrained('antrean')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis', 24);
            $table->text('pesan');
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamps();

            $table->unique(['antrean_id', 'jenis'], 'notifikasi_unik_per_jenis');
        });

        DB::statement("ALTER TABLE notifikasi MODIFY jenis ENUM('REMINDER_3_ANTREAN','DIPANGGIL') NOT NULL");
        DB::statement('CREATE INDEX notifikasi_user_idx ON notifikasi (user_id, created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
