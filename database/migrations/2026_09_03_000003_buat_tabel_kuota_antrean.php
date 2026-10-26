<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD proposal: entitas KUOTA_ANTREAN — batas jumlah antrean harian per poli (US-18).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kuota_antrean', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poli_id')->constrained('polis')->cascadeOnDelete();
            $table->unsignedInteger('jumlah_kuota');
            $table->date('tanggal_berlaku');
            $table->timestamps();
            $table->unique(['poli_id', 'tanggal_berlaku'], 'kuota_antrean_unik_per_tanggal');
        });

        DB::statement('ALTER TABLE kuota_antrean ADD CONSTRAINT kuota_antrean_positif CHECK (jumlah_kuota > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kuota_antrean');
    }
};
