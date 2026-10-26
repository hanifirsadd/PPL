<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERD proposal: entitas NOTIFIKASI — pemberitahuan tersisa 3 antrean
 * (REMINDER_3_ANTREAN, US-08) dan nomor dipanggil (DIPANGGIL, US-09).
 * DIBUAT_PADA ERD dipetakan ke kolom bawaan created_at.
 */
class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi';

    protected $fillable = [
        'antrean_id',
        'user_id',
        'jenis',
        'pesan',
        'dibaca_pada',
    ];

    protected $casts = [
        'dibaca_pada' => 'datetime',
    ];

    public const REMINDER_TIGA = 'REMINDER_3_ANTREAN';
    public const DIPANGGIL = 'DIPANGGIL';

    public function antrean(): BelongsTo
    {
        return $this->belongsTo(Antrean::class, 'antrean_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sudahDibaca(): bool
    {
        return $this->dibaca_pada !== null;
    }
}
