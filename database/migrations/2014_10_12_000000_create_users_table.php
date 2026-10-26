<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            // hash bcrypt (KNF-02: hashing password dasar)
            $table->string('password');
            // ERD USERS: atribut Role membedakan PASIEN / PETUGAS / ADMIN
            $table->string('peran', 16)->default('PASIEN');
            $table->rememberToken();
            $table->timestamps();
        });

        // otorisasi berbasis peran (KNF-02)
        DB::statement("ALTER TABLE users MODIFY peran ENUM('PASIEN','PETUGAS','ADMIN') NOT NULL DEFAULT 'PASIEN'");
        DB::statement('CREATE INDEX users_peran_idx ON users (peran)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
