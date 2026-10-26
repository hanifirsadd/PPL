<?php

namespace App\Http\Controllers;

use App\Models\Antrean;
use App\Models\Notifikasi;
use App\Models\Poli;
use App\Services\AntreanService;
use App\Services\NotifikasiService;
use App\Support\Urutan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Class diagram proposal: AntreanPasienController menyediakan fungsi bagi
 * pasien — melihat daftar poli (US-03), mengambil nomor antrean (US-04),
 * memantau posisi & status (US-06/US-07), serta membaca notifikasi
 * (US-08/US-09) dan riwayat antrean.
 */
class AntreanPasienController extends Controller
{
    /** Pilihan pengurutan tiap daftar yang ditampilkan pasien. */
    public const PILIHAN_URUT_POLI = ['nama', 'kode', 'kuota'];
    public const PILIHAN_URUT_RIWAYAT = [
        'tanggal' => 'tanggal_antrean',
        'nomor' => 'nomor_antrean',
        'status' => 'status',
        'poli' => 'poli_id',
    ];
    public const PILIHAN_URUT_NOTIFIKASI = [
        'waktu' => 'created_at',
        'jenis' => 'jenis',
        'antrean' => 'antrean_id',
    ];

    public function __construct(
        private readonly AntreanService $antrean,
        private readonly NotifikasiService $notifikasi,
    ) {
    }

    /** US-03 — daftar poli aktif beserta status kuota dan jam operasional. */
    public function pilihPoli(Request $request): View
    {
        $tanggal = Carbon::today();

        $daftarPoli = Poli::query()
            ->where('status_aktif', true)
            ->get()
            ->map(fn (Poli $poli) => [
                'poli' => $poli,
                'sedang_buka' => $poli->sedangBuka(),
                'sisa_kuota' => $poli->sisaKuota($tanggal),
            ]);

        // Pengurutan kartu poli (diurutkan di PHP karena sisa kuota dihitung saat render).
        $urutkan = in_array($request->query('urutkan'), self::PILIHAN_URUT_POLI, true)
            ? $request->query('urutkan')
            : 'nama';
        $arah = Urutan::arah($request);

        $daftarPoli = match ($urutkan) {
            'kode' => $daftarPoli->sortBy(fn ($item) => $item['poli']->kode_poli, SORT_NATURAL, $arah === 'desc')->values(),
            'kuota' => $daftarPoli->sortBy(
                fn ($item) => $item['sisa_kuota'] ?? PHP_INT_MAX,
                SORT_NUMERIC,
                $arah === 'desc'
            )->values(),
            default => $daftarPoli->sortBy(fn ($item) => $item['poli']->nama_poli, SORT_NATURAL, $arah === 'desc')->values(),
        };

        return view('pasien.poli', [
            'daftarPoli' => $daftarPoli,
            'antreanAktif' => $request->user()->antreanAktif($tanggal->toDateString()),
            'pilihanUrutan' => ['nama' => 'nama_poli', 'kode' => 'kode_poli', 'kuota' => 'sisa_kuota'],
            'bawaanUrutan' => 'nama',
            'urutanAktif' => $urutkan,
            'arahUrutan' => $arah,
        ]);
    }

    /** US-04 — ambil nomor antrean online (kuota + validasi antrean aktif). */
    public function ambil(Request $request): RedirectResponse
    {
        $request->validate(['id_poli' => ['required', 'exists:polis,id']], [], [
            'id_poli' => 'poli',
        ]);

        $antrean = $this->antrean->ambilOnline($request->user(), (int) $request->input('id_poli'));

        return redirect()
            ->route('pasien.status')
            ->with('sukses', sprintf('Nomor antrean %s berhasil diambil.', $antrean->nomor_antrean));
    }

    /** US-06 & US-07 — posisi, nomor yang sedang dilayani, dan status antrean. */
    public function status(Request $request): View
    {
        return view('pasien.status', array_merge(
            $this->dataStatus($request),
            ['sukses' => $request->session()->get('sukses')],
        ));
    }

    /**
     * Fragmen status untuk penyegaran otomatis (AJAX) — agar posisi yang
     * dilihat pasien selalu sama dengan perubahan yang dilakukan petugas.
     */
    public function fragmenStatus(Request $request): View
    {
        $data = $this->dataStatus($request);

        return view($data['antrean'] === null
            ? 'pasien.partials.kosong'
            : 'pasien.partials.status', $data);
    }

    /** @return array{antrean: ?Antrean, sisa_di_depan: int, nomor_sedang_dilayani: ?string, jumlah_total: int} */
    private function dataStatus(Request $request): array
    {
        $data = $this->antrean->statusPasien($request->user());

        if ($data === null) {
            return ['antrean' => null, 'sisa_di_depan' => 0, 'nomor_sedang_dilayani' => null, 'jumlah_total' => 0];
        }

        return [
            'antrean' => $data['antrean'],
            'sisa_di_depan' => $data['sisa_di_depan'],
            'nomor_sedang_dilayani' => $data['nomor_sedang_dilayani'],
            'jumlah_total' => $data['jumlah_total'],
        ];
    }

    /** Riwayat antrean milik pasien yang login. */
    public function riwayat(Request $request): View
    {
        $pilihan = self::PILIHAN_URUT_RIWAYAT;
        $bawaan = 'tanggal';
        // Kolom bawaan (terbaru lebih dulu); kolom lain default naik.
        $arah = Urutan::arah($request, Urutan::kunci($request, $pilihan, $bawaan) === $bawaan ? 'desc' : 'asc');

        $riwayat = Urutan::terapkan(
            Antrean::query()
                ->with('poli')
                ->where('user_id', $request->user()->id),
            $request,
            $pilihan,
            $bawaan,
            $arah,
        )->get();

        return view('pasien.riwayat', [
            'riwayat' => $riwayat,
            'pilihanUrutan' => $pilihan,
            'bawaanUrutan' => $bawaan,
            'urutanAktif' => Urutan::kunci($request, $pilihan, $bawaan),
            'arahUrutan' => $arah,
        ]);
    }

    /** US-08 & US-09 — daftar notifikasi (reminder & pemanggilan). */
    public function notifikasi(Request $request): View
    {
        $pilihan = self::PILIHAN_URUT_NOTIFIKASI;
        $bawaan = 'waktu';
        // Kolom bawaan (terbaru lebih dulu); kolom lain default naik.
        $arah = Urutan::arah($request, Urutan::kunci($request, $pilihan, $bawaan) === $bawaan ? 'desc' : 'asc');

        $daftar = Urutan::terapkan(
            Notifikasi::query()->where('user_id', $request->user()->id)->with('antrean.poli'),
            $request,
            $pilihan,
            $bawaan,
            $arah,
        )
            // Notifikasi yang dibuat pada detik yang sama tetap terurut rapi.
            ->orderBy('id', $arah)
            ->get();

        return view('pasien.notifikasi', [
            'daftar' => $daftar,
            'pilihanUrutan' => $pilihan,
            'bawaanUrutan' => $bawaan,
            'urutanAktif' => Urutan::kunci($request, $pilihan, $bawaan),
            'arahUrutan' => $arah,
        ]);
    }

    public function tandaiDibaca(Request $request, int $id): RedirectResponse
    {
        $this->notifikasi->tandaiDibaca($request->user()->id, $id);

        return back()->with('sukses', 'Notifikasi ditandai sudah dibaca.');
    }

    public function tandaiSemuaDibaca(Request $request): RedirectResponse
    {
        $this->notifikasi->tandaiSemuaDibaca($request->user()->id);

        return back()->with('sukses', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
