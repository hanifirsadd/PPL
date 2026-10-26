<?php

namespace App\Models;

use App\Enums\StatusAntrean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Class diagram proposal: kelas User menyimpan akun beserta peran;
 * method isPasien, isPetugas, dan isAdmin menentukan hak akses.
 */
class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'users';

    protected $fillable = [
        'nama',
        'email',
        'password',
        'peran',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        // KNF-02: password selalu disimpan sebagai hash (bcrypt), bukan teks polos
        'password' => 'hashed',
    ];

    public const PERAN_PASIEN = 'PASIEN';
    public const PERAN_PETUGAS = 'PETUGAS';
    public const PERAN_ADMIN = 'ADMIN';

    public function isPasien(): bool
    {
        return $this->peran === self::PERAN_PASIEN;
    }

    public function isPetugas(): bool
    {
        return $this->peran === self::PERAN_PETUGAS;
    }

    public function isAdmin(): bool
    {
        return $this->peran === self::PERAN_ADMIN;
    }

    /** @return HasMany<Antrean, $this> */
    public function antrean(): HasMany
    {
        return $this->hasMany(Antrean::class, 'user_id');
    }

    /** @return HasMany<Notifikasi, $this> */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'user_id');
    }

    /** Nama tampil per karakteristik pengguna: nama asli atau email. */
    public function namaTampil(): string
    {
        return $this->nama ?: $this->email;
    }

    /** Status antrean aktif milik pasien ini pada tanggal tertentu. */
    public function antreanAktif(string $tanggal): ?Antrean
    {
        return $this->antrean()
            ->where('tanggal_antrean', $tanggal)
            ->whereIn('status', array_map(fn (StatusAntrean $s) => $s->value, StatusAntrean::aktif()))
            ->latest('id')
            ->first();
    }
}
