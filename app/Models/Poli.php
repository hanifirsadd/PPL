<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class diagram proposal: kelas Poli merepresentasikan data poli dengan
 * method sedangBuka (cek jam operasional) dan sisaKuota (kuota tanggal
 * tertentu). ERD: Kode_poli unik, Is_active menentukan tampil/tidak.
 */
class Poli extends Model
{
    use HasFactory;

    protected $table = 'polis';

    protected $fillable = [
        'kode_poli',
        'nama_poli',
        'deskripsi',
        'status_aktif',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    /** @return HasMany<JamOperasional, $this> */
    public function jamOperasional(): HasMany
    {
        return $this->hasMany(JamOperasional::class, 'poli_id');
    }

    /** @return HasMany<KuotaAntrean, $this> */
    public function kuotaAntrean(): HasMany
    {
        return $this->hasMany(KuotaAntrean::class, 'poli_id');
    }

    /** @return HasMany<Antrean, $this> */
    public function antrean(): HasMany
    {
        return $this->hasMany(Antrean::class, 'poli_id');
    }

    /** US-03/US-04 prakondisi: poli sedang dalam jam operasional. */
    public function sedangBuka(?Carbon $waktu = null): bool
    {
        $waktu = $waktu ?? Carbon::now();
        $hari = ['MINGGU', 'SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'][(int) $waktu->format('w')];
        $jam = $waktu->format('H:i:s');

        return $this->jamOperasional()
            ->where('hari', $hari)
            ->where('jam_buka', '<=', $jam)
            ->where('jam_tutup', '>', $jam)
            ->exists();
    }

    /**
     * Sisa kuota harian. null = admin belum menetapkan kuota untuk tanggal
     * tersebut, sehingga antrean tidak dibatasi (keputusan desain; proposal
     * hanya mengatur kuota bila ditetapkan admin / US-18).
     */
    public function sisaKuota(?Carbon $tanggal = null): ?int
    {
        $tanggal = ($tanggal ?? Carbon::now())->toDateString();

        $kuota = $this->kuotaAntrean()->where('tanggal_berlaku', $tanggal)->first();
        if ($kuota === null) {
            return null;
        }

        $terpakai = $this->antrean()->where('tanggal_antrean', $tanggal)->count();

        return max(0, $kuota->jumlah_kuota - $terpakai);
    }
}
