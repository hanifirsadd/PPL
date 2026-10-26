<?php

namespace App\Http\Controllers;

use App\Enums\StatusAntrean;
use App\Models\Poli;
use App\Services\AntreanService;
use App\Support\Urutan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Class diagram proposal: AntreanPetugasController — daftar antrean harian
 * per poli (US-10), input antrean manual (US-05), panggil (US-11), mulai
 * layani (US-12), selesai (US-13), lewati (US-14), dan panggil ulang (US-15).
 *
 * Seluruh aturan status berada di AntreanService (KNF-03).
 */
class AntreanPetugasController extends Controller
{
    public function __construct(private readonly AntreanService $antrean)
    {
    }

    /** US-10 — daftar antrean hari ini, dapat difilter per poli & status. */
    public function daftar(Request $request): View
    {
        [$daftar, $daftarPoli, $poliTerpilih, $statusTerpilih] = $this->dataDaftar($request);

        return view('petugas.antrean', [
            'daftar' => $daftar,
            'daftarPoli' => $daftarPoli,
            'poliTerpilih' => $poliTerpilih,
            'statusTerpilih' => $statusTerpilih,
            'semuaStatus' => StatusAntrean::cases(),
            'sukses' => $request->session()->get('sukses'),
            'pilihanUrutan' => AntreanService::PILIHAN_URUT,
            'bawaanUrutan' => 'nomor',
            'urutanAktif' => Urutan::kunci($request, AntreanService::PILIHAN_URUT, 'nomor'),
            'arahUrutan' => Urutan::arah($request),
        ]);
    }

    /**
     * Fragmen tabel untuk penyegaran otomatis (AJAX): isi tabel diperbarui
     * tanpa memuat ulang halaman, sehingga antrean yang baru diambil pasien
     * langsung terlihat oleh petugas.
     */
    public function fragmenDaftar(Request $request): View
    {
        [$daftar] = $this->dataDaftar($request);

        return view('petugas.partials.daftar', [
            'daftar' => $daftar,
            'pilihanUrutan' => AntreanService::PILIHAN_URUT,
            'bawaanUrutan' => 'nomor',
            'urutanAktif' => Urutan::kunci($request, AntreanService::PILIHAN_URUT, 'nomor'),
            'arahUrutan' => Urutan::arah($request),
        ]);
    }

    /** @return array{0: Collection, 1: Collection, 2: ?int, 3: ?string} */
    private function dataDaftar(Request $request): array
    {
        $idPoli = $request->query('poli') ? (int) $request->query('poli') : null;

        $statusDiminta = $request->query('status');
        $status = in_array($statusDiminta, array_column(StatusAntrean::cases(), 'value'), true)
            ? $statusDiminta
            : null;

        $urutkan = (string) $request->query('urutkan', 'nomor');

        return [
            $this->antrean->daftarHariIni($idPoli, $status, $urutkan, Urutan::arah($request)),
            Poli::orderBy('nama_poli')->get(),
            $idPoli,
            $status,
        ];
    }

    /** US-05 — form input antrean manual untuk pasien walk-in. */
    public function formManual(): View
    {
        return view('petugas.manual', [
            'daftarPoli' => Poli::where('status_aktif', true)->orderBy('nama_poli')->get(),
        ]);
    }

    /** US-05 — simpan antrean manual ke daftar yang sama dengan antrean online. */
    public function simpanManual(Request $request): RedirectResponse
    {
        $request->validate([
            'id_poli' => ['required', 'exists:polis,id'],
            'nama' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:254'],
        ], [], [
            'id_poli' => 'poli',
            'nama' => 'nama pasien',
            'email' => 'email pasien',
        ]);

        $antrean = $this->antrean->tambahManual(
            (int) $request->input('id_poli'),
            $request->input('nama'),
            $request->input('email'),
            $request->user(),
        );

        return redirect()
            ->route('petugas.antrean', ['poli' => $antrean->poli_id])
            ->with('sukses', sprintf('Antrean manual %s berhasil dibuat.', $antrean->nomor_antrean));
    }

    /** US-11 — panggil pasien berikutnya pada sebuah poli. */
    public function panggil(Request $request): RedirectResponse
    {
        $request->validate(['id_poli' => ['required', 'exists:polis,id']], [], ['id_poli' => 'poli']);

        $antrean = $this->antrean->panggilBerikutnya((int) $request->input('id_poli'));

        if ($antrean === null) {
            return back()->withErrors(['antrean' => 'Tidak ada pasien yang menunggu di poli ini.']);
        }

        return back()->with('sukses', sprintf('Memanggil nomor %s.', $antrean->nomor_antrean));
    }

    /** US-11 — panggil satu pasien tertentu (baris tertentu pada daftar). */
    public function panggilSpesifik(int $id): RedirectResponse
    {
        $antrean = $this->antrean->panggilAntrean($id);

        return back()->with('sukses', sprintf('Memanggil nomor %s.', $antrean->nomor_antrean));
    }

    /** US-12 — mulai pelayanan (DIPANGGIL → DILAYANI). */
    public function mulai(Request $request, int $id): RedirectResponse
    {
        $antrean = $this->antrean->mulaiPelayanan($id);

        return back()->with('sukses', sprintf('Nomor %s sedang dilayani.', $antrean->nomor_antrean));
    }

    /** US-13 — selesaikan pelayanan (DILAYANI → SELESAI). */
    public function selesai(Request $request, int $id): RedirectResponse
    {
        $antrean = $this->antrean->selesaiPelayanan($id);

        return back()->with('sukses', sprintf('Nomor %s selesai dilayani.', $antrean->nomor_antrean));
    }

    /** US-14 — lewati pasien yang tidak hadir (DIPANGGIL → DILEWATI). */
    public function lewati(Request $request, int $id): RedirectResponse
    {
        $antrean = $this->antrean->lewatiPelayanan($id);

        return back()->with('sukses', sprintf('Nomor %s dilewati.', $antrean->nomor_antrean));
    }

    /** US-15 — panggil ulang pasien yang pernah dilewati (DILEWATI → DIPANGGIL). */
    public function panggilUlang(Request $request, int $id): RedirectResponse
    {
        $antrean = $this->antrean->panggilUlang($id);

        return back()->with('sukses', sprintf('Nomor %s dipanggil ulang.', $antrean->nomor_antrean));
    }
}
