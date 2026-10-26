<?php

namespace App\Models;

use App\Enums\StatusAntrean;
use App\Enums\TipeAntrean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class diagram proposal: kelas Antrean menyimpan nomor, tanggal, tipe, dan
 * status; method posisi menentukan urutan pasien. ERD: user_id boleh NULL
 * untuk walk-in (nama_pasien_manual), dibuat_oleh = petugas pencatat.
 */
class Antrean extends Model
{
    use HasFactory;

    protected $table = 'antrean';

    protected $fillable = [
        'nomor_antrean',
        'user_id',
        'nama_pasien_manual',
        'poli_id',
        'dibuat_oleh',
        'tanggal_antrean',
        'tipe',
        'status',
        'waktu_dipanggil',
        'waktu_mulai',
        'waktu_selesai',
    ];

    protected $casts = [
        'tanggal_antrean' => 'date',
        'tipe' => TipeAntrean::class,
        'status' => StatusAntrean::class,
        'waktu_dipanggil' => 'datetime',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class, 'poli_id');
    }

    /** Petugas yang mencatat antrean ini (ERD: Dibuat_oleh). */
    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return HasMany<Notifikasi, $this> */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'antrean_id');
    }

    /**
     * Class diagram proposal: "method posisi digunakan untuk menentukan
     * urutan pasien dalam antrean."
     *
     * @return int jumlah antrean yang masih berada di depan nomor ini
     *              (status aktif dengan nomor lebih kecil pada poli & hari sama)
     */
    public function posisi(): int
    {
        $statusAktif = array_map(fn (StatusAntrean $s) => $s->value, StatusAntrean::aktif());

        return $this->antreanDiDepan($statusAktif);
    }

    /**
     * @param  list<string>  $statusAktif
     * @return int
     */
    public function antreanDiDepan(array $statusAktif): int
    {
        return static::query()
            ->where('poli_id', $this->poli_id)
            ->where('tanggal_antrean', $this->tanggal_antrean)
            ->whereIn('status', $statusAktif)
            ->where('nomor_antrean', '<', $this->nomor_antrean)
            ->where('id', '!=', $this->id)
            ->count();
    }

    /** Nama pasien tampilan: akun terdaftar atau identitas manual walk-in. */
    public function namaPasien(): string
    {
        return $this->pasien?->nama ?: ($this->nama_pasien_manual ?: '—');
    }
}
