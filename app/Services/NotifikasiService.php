<?php

namespace App\Services;

use App\Models\Antrean;
use App\Models\Notifikasi;
use Illuminate\Support\Collection;

/**
 * Class diagram proposal: NotifikasiService "bertanggung jawab membuat data
 * Notifikasi", dipanggil AntreanService. Notifikasi hanya in-app (WhatsApp /
 * SMS di luar ruang lingkup proposal).
 */
class NotifikasiService
{
    /** US-09: pemberitahuan saat nomor antrean resmi dipanggil. */
    public function kirimDipanggil(Antrean $antrean): void
    {
        // Pasien walk-in tanpa akun tidak memiliki kotak notifikasi.
        if ($antrean->user_id === null) {
            return;
        }

        // updateOrCreate: pemanggilan ulang (re-call) memperbarui pesan yang
        // sama alih-alih membuat duplikat — constraint UNIQUE (antrean, jenis).
        Notifikasi::query()->updateOrCreate(
            ['antrean_id' => $antrean->id, 'jenis' => Notifikasi::DIPANGGIL],
            [
                'user_id' => $antrean->user_id,
                'pesan' => sprintf(
                    'Nomor antrean %s (%s) sedang dipanggil. Silakan menuju loket.',
                    $antrean->nomor_antrean,
                    $antrean->poli?->nama_poli ?? 'Poli',
                ),
                'dibaca_pada' => null,
                'created_at' => now(),
            ],
        );
    }

    /**
     * US-08: reminder otomatis ketika tersisa N antrean (default 3) di depan
     * pasien. UNIQUE (antrean_id, jenis) di database menjamin reminder tidak
     * dikirim berulang untuk antrean yang sama.
     */
    public function kirimReminderTigaAntrean(int $poliId, string $tanggal): void
    {
        $ambang = (int) config('antrean.reminder_sisa', 3);
        $statusAktif = array_map(fn ($s) => $s->value, \App\Enums\StatusAntrean::aktif());

        $menunggu = Antrean::query()
            ->where('poli_id', $poliId)
            ->where('tanggal_antrean', $tanggal)
            ->where('status', 'MENUNGGU')
            ->whereNotNull('user_id')
            ->with('poli')
            ->get();

        foreach ($menunggu as $antrean) {
            $sisa = $antrean->antreanDiDepan($statusAktif);

            // Sisa 0 berarti pasien adalah giliran berikutnya — bukan
            // "peringatan 3 antrean"; pengingat dikirim saat 1..ambang.
            if ($sisa < 1 || $sisa > $ambang) {
                continue;
            }

            Notifikasi::query()->firstOrCreate(
                ['antrean_id' => $antrean->id, 'jenis' => Notifikasi::REMINDER_TIGA],
                [
                    'user_id' => $antrean->user_id,
                    'pesan' => sprintf(
                        'Tersisa %d antrean sebelum giliran Anda (%s) di %s. Bersiaplah menuju puskesmas.',
                        $sisa,
                        $antrean->nomor_antrean,
                        $antrean->poli?->nama_poli ?? 'Poli',
                    ),
                ],
            );
        }
    }

    /** @return Collection<int, Notifikasi> */
    public function daftar(?int $userId): Collection
    {
        if ($userId === null) {
            return collect();
        }

        return Notifikasi::query()
            ->where('user_id', $userId)
            ->with('antrean.poli')
            ->latest('id')
            ->get();
    }

    public function tandaiDibaca(int $userId, int $notifikasiId): bool
    {
        return (bool) Notifikasi::query()
            ->where('id', $notifikasiId)
            ->where('user_id', $userId)
            ->update(['dibaca_pada' => now()]);
    }

    public function tandaiSemuaDibaca(int $userId): void
    {
        Notifikasi::query()
            ->where('user_id', $userId)
            ->whereNull('dibaca_pada')
            ->update(['dibaca_pada' => now()]);
    }
}
