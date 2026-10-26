<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ERD proposal: entitas KUOTA_ANTREAN, relasi "dibatasi" dengan POLIS. */
class KuotaAntrean extends Model
{
    use HasFactory;

    protected $table = 'kuota_antrean';

    protected $fillable = [
        'poli_id',
        'jumlah_kuota',
        'tanggal_berlaku',
    ];

    protected $casts = [
        'jumlah_kuota' => 'integer',
        'tanggal_berlaku' => 'date',
    ];

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class, 'poli_id');
    }
}
