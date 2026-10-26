<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD proposal: entitas JAM_OPERASIONAL — jadwal pelayanan tiap poli (US-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_operasional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poli_id')->constrained('polis')->cascadeOnDelete();
            $table->string('hari', 8);
            $table->time('jam_buka');
            $table->time('jam_tutup');
            $table->timestamps();
            $table->unique(['poli_id', 'hari'], 'jam_operasional_unik_per_hari');
        });

        DB::statement("ALTER TABLE jam_operasional MODIFY hari ENUM('SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU','MINGGU') NOT NULL");
        // jam_buka < jam_tutup
        DB::statement('ALTER TABLE jam_operasional ADD CONSTRAINT jam_operasional_rentang_valid CHECK (jam_buka < jam_tutup)');
    }

    public function down(): void
    {
        Schema::dropIfExists('jam_operasional');
    }
};
