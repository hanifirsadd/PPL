<?php

namespace App\Services;

use App\Enums\StatusAntrean;
use App\Enums\TipeAntrean;
use App\Exceptions\AturanBisnisException;
use App\Models\Antrean;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class diagram proposal: AntreanService adalah komponen utama — pengambilan
 * antrean online, antrean manual, dan seluruh perpindahan status (panggil,
 * layani, selesai, lewati, panggil ulang), termasuk perhitungan antrean di
 * depan pasien. Aturan bisnis dipisahkan dari controller agar mudah diuji
 * otomatis (KNF-03).
 */
class AntreanService
{
    /** Percobaan ulang saat nomor antrean berbenturan karena akses bersamaan. */
    private const MAKS_COBA = 5;

    /** Pilihan pengurutan daftar antrean (kunci => kolom database). */
    public const PILIHAN_URUT = [
        'nomor' => 'nomor_antrean',
        'status' => 'status',
        'waktu' => 'waktu_dipanggil',
        'pasien' => 'nama_pasien_manual',
    ];

    public function __construct(private readonly NotifikasiService $notifikasi)
    {
    }

    /* ---------------------------------------------------------------------
     | US-04 — Pengambilan antrean online (prakondisi KNF-01 & kuota)
     * ------------------------------------------------------------------ */
    public function ambilOnline(User $pasien, int $poliId): Antrean
    {
        $poli = $this->poliDapatDipakai($poliId);
        $tanggal = Carbon::today();

        if ($this->punyaAntreanAktif($pasien->id, $poliId, $tanggal)) {
            throw new AturanBisnisException(
                'Anda sudah mengambil nomor antrean aktif di poli ini hari ini. Silakan tunggu giliran Anda.'
            );
        }

        return $this->simpanAntrean([
            'user_id' => $pasien->id,
            'nama_pasien_manual' => null,
            'poli_id' => $poliId,
            'dibuat_oleh' => null,
            'tanggal_antrean' => $tanggal->toDateString(),
            'tipe' => TipeAntrean::ONLINE->value,
        ], $poli, $tanggal);
    }

    /* ---------------------------------------------------------------------
     | US-05 — Input antrean manual petugas (pasien walk-in)
     * ------------------------------------------------------------------ */
    public function tambahManual(int $poliId, ?string $namaManual, ?string $emailPasien, User $petugas): Antrean
    {
        $poli = $this->poliDapatDipakai($poliId);
        $tanggal = Carbon::today();

        $pasien = null;
        if ($emailPasien !== null && trim($emailPasien) !== '') {
            $pasien = User::query()
                ->where('email', trim($emailPasien))
                ->where('peran', User::PERAN_PASIEN)
                ->first();

            if ($pasien === null) {
                throw new AturanBisnisException(
                    'Email pasien tidak terdaftar. Kosongkan email bila pasien datang tanpa akun.'
                );
            }

            if ($this->punyaAntreanAktif($pasien->id, $poliId, $tanggal)) {
                throw new AturanBisnisException(
                    'Pasien ini sudah memiliki antrean aktif di poli tersebut hari ini.'
                );
            }
        }

        if ($pasien === null && ($namaManual === null || trim($namaManual) === '')) {
            throw new AturanBisnisException('Identitas pasien wajib diisi (nama) untuk pasien tanpa akun.');
        }

        return $this->simpanAntrean([
            'user_id' => $pasien?->id,
            'nama_pasien_manual' => $pasien ? null : trim($namaManual ?? ''),
            'poli_id' => $poliId,
            'dibuat_oleh' => $petugas->id,
            'tanggal_antrean' => $tanggal->toDateString(),
            'tipe' => TipeAntrean::MANUAL->value,
        ], $poli, $tanggal);
    }

    /* ---------------------------------------------------------------------
     | US-06 & US-07 — Monitoring posisi & status antrean pasien
     * ------------------------------------------------------------------ */

    /**
     * @return array{
     *     antrean: Antrean,
     *     sisa_di_depan: int,
     *     nomor_sedang_dilayani: ?string,
     *     jumlah_total: int
     * } | null
     */
    public function statusPasien(User $pasien): ?array
    {
        $tanggal = Carbon::today();

        $antrean = $pasien->antreanAktif($tanggal->toDateString())
            ?? Antrean::query()
                ->where('user_id', $pasien->id)
                ->where('tanggal_antrean', $tanggal->toDateString())
                ->with('poli')
                ->latest('id')
                ->first();

        if ($antrean === null) {
            return null;
        }

        return [
            'antrean' => $antrean,
            'sisa_di_depan' => $antrean->posisi(),
            'nomor_sedang_dilayani' => $this->nomorSedangDiproses($antrean->poli_id, $tanggal),
            'jumlah_total' => Antrean::query()
                ->where('poli_id', $antrean->poli_id)
                ->where('tanggal_antrean', $tanggal->toDateString())
                ->count(),
        ];
    }

    /* ---------------------------------------------------------------------
     | US-10 — Daftar antrean hari ini (dapat disaring & diurutkan)
     * ------------------------------------------------------------------ */

    /** @return Collection<int, Antrean> */
    public function daftarHariIni(?int $poliId = null, ?string $status = null, ?string $urutkan = null, string $arah = 'asc'): Collection
    {
        $urutan = in_array($urutkan, self::PILIHAN_URUT, true) ? $urutkan : 'nomor';

        return Antrean::query()
            ->with(['pasien', 'poli'])
            ->where('tanggal_antrean', Carbon::today()->toDateString())
            ->when($poliId, fn ($q) => $q->where('poli_id', $poliId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($urutan === 'pasien', fn ($q) => $q
                ->leftJoin('users as pemesan', 'pemesan.id', '=', 'antrean.user_id')
                ->select('antrean.*')
                ->orderByRaw('COALESCE(pemesan.nama, antrean.nama_pasien_manual) ' . ($arah === 'desc' ? 'DESC' : 'ASC')))
            ->when($urutan === 'status', fn ($q) => $q
                ->orderByRaw(\App\Support\Urutan::urutanStatus($arah)))
            ->when($urutan === 'waktu', fn ($q) => $q
                ->orderByRaw('(waktu_dipanggil IS NULL) ' . ($arah === 'desc' ? 'DESC' : 'ASC'))
                ->orderBy('waktu_dipanggil', $arah))
            ->when($urutan !== 'pasien', fn ($q) => $q
                ->orderBy('poli_id')
                ->orderBy(self::PILIHAN_URUT[$urutan], $arah))
            ->get();
    }

    /* ---------------------------------------------------------------------
     | US-11 — Panggil pasien berikutnya
     * ------------------------------------------------------------------ */
    public function panggilBerikutnya(int $poliId): ?Antrean
    {
        $tanggal = Carbon::today();

        return DB::transaction(function () use ($poliId, $tanggal) {
            $berikut = Antrean::query()
                ->where('poli_id', $poliId)
                ->where('tanggal_antrean', $tanggal->toDateString())
                ->where('status', StatusAntrean::MENUNGGU->value)
                ->orderByRaw('nomor_antrean')
                ->lockForUpdate()
                ->first();

            if ($berikut === null) {
                return null;
            }

            $this->ubahStatus($berikut, StatusAntrean::DIPANGGIL, [
                'waktu_dipanggil' => now(),
            ]);
            $this->notifikasi->kirimDipanggil($berikut);
            $this->notifikasi->kirimReminderTigaAntrean($poliId, $tanggal->toDateString());

            return $berikut;
        });
    }

    /* ---------------------------------------------------------------------
     | US-12 — Mulai pelayanan
     * ------------------------------------------------------------------ */
    /* ------------------- US-11 — panggil satu pasien tertentu ---------------- */
    public function panggilAntrean(int $antreanId): Antrean
    {
        return DB::transaction(function () use ($antreanId) {
            $antrean = $this->kunci($antreanId);
            $this->pastikanTransisi($antrean, StatusAntrean::DIPANGGIL, '');

            $antrean->update([
                'status' => StatusAntrean::DIPANGGIL->value,
                'waktu_dipanggil' => now(),
            ]);

            $this->notifikasi->kirimDipanggil($antrean);
            $this->notifikasi->kirimReminderTigaAntrean($antrean->poli_id, $antrean->tanggal_antrean->toDateString());

            return $antrean;
        });
    }

    public function mulaiPelayanan(int $antreanId): Antrean
    {
        return DB::transaction(function () use ($antreanId) {
            $antrean = $this->kunci($antreanId);
            $this->pastikanTransisi($antrean, StatusAntrean::DILAYANI, 'Pasien hanya dapat dilayani setelah dipanggil.');

            try {
                $antrean->update([
                    'status' => StatusAntrean::DILAYANI->value,
                    'waktu_mulai' => now(),
                ]);
            } catch (QueryException $e) {
                if ($this->konflik($e, 'antrean_satu_dilayani_per_poli')) {
                    throw new AturanBisnisException('Sudah ada pasien yang sedang dilayani di poli ini.');
                }
                throw $e;
            }

            $this->notifikasi->kirimReminderTigaAntrean($antrean->poli_id, $antrean->tanggal_antrean->toDateString());

            return $antrean;
        });
    }

    /* ---------------------------------------------------------------------
     | US-13 — Selesaikan pelayanan
     * ------------------------------------------------------------------ */
    public function selesaiPelayanan(int $antreanId): Antrean
    {
        return DB::transaction(function () use ($antreanId) {
            $antrean = $this->kunci($antreanId);
            $this->pastikanTransisi($antrean, StatusAntrean::SELESAI, 'Pelayanan hanya dapat diselesaikan setelah pasien dilayani.');

            $antrean->update([
                'status' => StatusAntrean::SELESAI->value,
                'waktu_selesai' => now(),
            ]);

            $this->notifikasi->kirimReminderTigaAntrean($antrean->poli_id, $antrean->tanggal_antrean->toDateString());

            return $antrean;
        });
    }

    /* ---------------------------------------------------------------------
     | US-14 — Lewati pasien yang tidak hadir
     * ------------------------------------------------------------------ */
    public function lewatiPelayanan(int $antreanId): Antrean
    {
        return DB::transaction(function () use ($antreanId) {
            $antrean = $this->kunci($antreanId);
            $this->pastikanTransisi($antrean, StatusAntrean::DILEWATI, 'Hanya pasien yang sedang dipanggil yang dapat dilewati.');

            $antrean->update(['status' => StatusAntrean::DILEWATI->value]);

            $this->notifikasi->kirimReminderTigaAntrean($antrean->poli_id, $antrean->tanggal_antrean->toDateString());

            return $antrean;
        });
    }

    /* ---------------------------------------------------------------------
     | US-15 — Panggil ulang pasien yang pernah dilewati
     * ------------------------------------------------------------------ */
    public function panggilUlang(int $antreanId): Antrean
    {
        return DB::transaction(function () use ($antreanId) {
            $antrean = $this->kunci($antreanId);
            $this->pastikanTransisi($antrean, StatusAntrean::DIPANGGIL, 'Hanya pasien yang pernah dilewati yang dapat dipanggil ulang.');

            $antrean->update([
                'status' => StatusAntrean::DIPANGGIL->value,
                'waktu_dipanggil' => now(),
            ]);
            $this->notifikasi->kirimDipanggil($antrean);
            $this->notifikasi->kirimReminderTigaAntrean($antrean->poli_id, $antrean->tanggal_antrean->toDateString());

            return $antrean;
        });
    }

    /* ---------------------------------------------------------------------
 | Papan antrean publik (untuk tamu) — hanya melihat, tanpa aksi
 * ------------------------------------------------------------------ */

    /**
     * Data papan antrean hari ini per poli: nomor yang sedang dipanggil/
     * dilayani dan antrean yang masih menunggu.
     *
     * @return array<int, array{poli: Poli, dipanggil: ?Antrean, dilayani: ?Antrean, menunggu: Collection<int, Antrean>}>
     */
    public function papanAntrean(): array
    {
        $hariIni = Carbon::today()->toDateString();

        $semua = Antrean::query()
            ->with('poli')
            ->where('tanggal_antrean', $hariIni)
            ->whereIn('status', [
                StatusAntrean::MENUNGGU->value,
                StatusAntrean::DIPANGGIL->value,
                StatusAntrean::DILAYANI->value,
            ])
            ->orderBy('nomor_antrean')
            ->get()
            ->groupBy('poli_id');

        return Poli::where('status_aktif', true)
            ->orderBy('nama_poli')
            ->get()
            ->map(function (Poli $poli) use ($semua) {
                $baris = $semua->get($poli->id, collect());

                return [
                    'poli' => $poli,
                    'dipanggil' => $baris->firstWhere('status', StatusAntrean::DIPANGGIL),
                    'dilayani' => $baris->firstWhere('status', StatusAntrean::DILAYANI),
                    'menunggu' => $baris->where('status', StatusAntrean::MENUNGGU)->values(),
                ];
            })
            ->all();
    }

    /* =================================================================
     | Pendukung
     * ============================================================== */

    /** Prakondisi US-03/US-04 & US-05: poli aktif, buka, dan kuota tersedia. */
    private function poliDapatDipakai(int $poliId): Poli
    {
        $poli = Poli::findOrFail($poliId);

        if (! $poli->status_aktif) {
            throw new AturanBisnisException('Poli tersebut tidak aktif.');
        }

        if (! $poli->sedangBuka()) {
            throw new AturanBisnisException('Poli tersebut sedang tutup (di luar jam operasional).');
        }

        $sisa = $poli->sisaKuota(Carbon::today());
        if ($sisa !== null && $sisa <= 0) {
            throw new AturanBisnisException('Kuota antrean poli ini untuk hari ini sudah penuh.');
        }

        return $poli;
    }

    /** KNF-01 di lapisan aplikasi (constraint database tetap jaga di belakang). */
    private function punyaAntreanAktif(int $userId, int $poliId, Carbon $tanggal): bool
    {
        return Antrean::query()
            ->where('user_id', $userId)
            ->where('poli_id', $poliId)
            ->where('tanggal_antrean', $tanggal->toDateString())
            ->whereIn('status', array_map(fn (StatusAntrean $s) => $s->value, StatusAntrean::aktif()))
            ->exists();
    }

    /**
     * Simpan antrean baru dengan nomor urut per poli per hari yang dibagikan
     * antara antrean online dan manual. Nomor dihitung ulang bila berbenturan
     * dengan pendaftaran bersamaan (constraint UNIQUE antrean_nomor_unik).
     */
    private function simpanAntrean(array $data, Poli $poli, Carbon $tanggal): Antrean
    {
        for ($coba = 1; $coba <= self::MAKS_COBA; $coba++) {
            try {
                return DB::transaction(function () use ($data, $poli, $tanggal) {
                    $data['nomor_antrean'] = $this->nomorBerikutnya($poli, $tanggal);
                    $antrean = Antrean::create($data);

                    $this->notifikasi->kirimReminderTigaAntrean($poli->id, $tanggal->toDateString());

                    return $antrean;
                });
            } catch (QueryException $e) {
                if ($this->konflik($e, 'antrean_nomor_unik') && $coba < self::MAKS_COBA) {
                    continue; // nomor bentrok karena akses bersamaan → hitung ulang
                }

                if ($this->konflik($e, 'antrean_satu_aktif_per_pasien')) {
                    throw new AturanBisnisException(
                        'Anda sudah memiliki antrean aktif di poli ini hari ini.'
                    );
                }

                throw $e;
            }
        }

        throw new AturanBisnisException('Gagal membuat nomor antrean. Silakan coba lagi.');
    }

    /**
     * Nomor antrean memakai awalan kode poli, misalnya PU-0001, PGT-0002.
     * Dengan begitu nomor selalu unik di seluruh puskesmas — nomor yang sama
     * tidak pernah muncul pada dua poli berbeda sehingga tidak pernah
     * tertukar antara tampilan pasien dan petugas.
     */
    private function nomorBerikutnya(Poli $poli, Carbon $tanggal): string
    {
        $tertinggi = DB::table('antrean')
            ->where('poli_id', $poli->id)
            ->where('tanggal_antrean', $tanggal->toDateString())
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(nomor_antrean, "-", -1) AS UNSIGNED)) as maks')
            ->value('maks');

        return sprintf('%s-%04d', strtoupper($poli->kode_poli), ((int) $tertinggi) + 1);
    }

    private function kunci(int $antreanId): Antrean
    {
        return Antrean::query()->lockForUpdate()->findOrFail($antreanId);
    }

    /** State machine (class diagram): aturan transisi ada di satu tempat. */
    private function pastikanTransisi(Antrean $antrean, StatusAntrean $tujuan, string $pesan): void
    {
        $saatIni = $antrean->status;

        if (! $saatIni->bolehKe($tujuan)) {
            throw new AturanBisnisException(
                sprintf('Status antrean %s (%s) tidak dapat diubah menjadi %s. %s',
                    $antrean->nomor_antrean,
                    $saatIni->label(),
                    $tujuan->label(),
                    $pesan,
                )
            );
        }
    }

    private function ubahStatus(Antrean $antrean, StatusAntrean $tujuan, array $atribut = []): void
    {
        $this->pastikanTransisi($antrean, $tujuan, '');
        $antrean->update(array_merge(['status' => $tujuan->value], $atribut));
    }

    private function nomorSedangDiproses(int $poliId, Carbon $tanggal): ?string
    {
        $proses = Antrean::query()
            ->where('poli_id', $poliId)
            ->where('tanggal_antrean', $tanggal->toDateString())
            ->whereIn('status', [
                StatusAntrean::DIPANGGIL->value,
                StatusAntrean::DILAYANI->value,
            ])
            ->orderByRaw("FIELD(status, 'DILAYANI', 'DIPANGGIL')")
            ->orderByRaw('nomor_antrean')
            ->get();

        return $proses->first()?->nomor_antrean;
    }

    private function konflik(QueryException $e, string $indeks): bool
    {
        return str_contains((string) $e->getMessage(), $indeks)
            || str_contains((string) ($e->errorInfo[2] ?? ''), $indeks);
    }
}
