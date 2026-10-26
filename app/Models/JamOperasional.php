<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ERD proposal: entitas JAM_OPERASIONAL, relasi "diatur" dengan POLIS. */
class JamOperasional extends Model
{
    use HasFactory;

    protected $table = 'jam_operasional';

    protected $fillable = [
        'poli_id',
        'hari',
        'jam_buka',
        'jam_tutup',
    ];

    public const HARI = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU'];

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class, 'poli_id');
    }
}
