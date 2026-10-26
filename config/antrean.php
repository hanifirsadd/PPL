<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambang reminder (US-08)
    |--------------------------------------------------------------------------
    |
    | Notifikasi "Tersisa N antrean sebelum giliran Anda" dikirim saat jumlah
    | antrean aktif di depan pasien mencapai ambang ini atau kurang.
    |
    */

    'reminder_sisa' => (int) env('REMINDER_SISA', 3),

];
