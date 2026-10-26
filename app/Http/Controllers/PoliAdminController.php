<?php

namespace App\Http\Controllers;

use App\Models\JamOperasional;
use App\Models\KuotaAntrean;
use App\Models\Poli;
use App\Support\Urutan;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Class diagram proposal: PoliAdminController — kelola data poli (US-16),
 * jam operasional (US-17), dan kuota antrean harian (US-18).
 */
class PoliAdminController extends Controller
{
    /* ------------------------------ US-16: Poli --------------------------- */

    /** Pilihan pengurutan tiap daftar yang ditampilkan admin. */
    public const PILIHAN_URUT_POLI = [
        'kode' => 'kode_poli',
        'nama' => 'nama_poli',
        'status' => 'status_aktif',
        'antrean' => 'antrean_count',
    ];
    public const PILIHAN_URUT_JAM = ['hari' => 'hari', 'buka' => 'jam_buka', 'tutup' => 'jam_tutup'];
    public const PILIHAN_URUT_KUOTA = [
        'tanggal' => 'tanggal_berlaku',
        'poli' => 'poli_id',
        'jumlah' => 'jumlah_kuota',
    ];

    public function index(Request $request): View
    {
        $daftarPoli = Urutan::terapkan(
            Poli::withCount('antrean'),
            $request,
            self::PILIHAN_URUT_POLI,
            'nama',
        )->get();

        return view('admin.poli', [
            'daftarPoli' => $daftarPoli,
            'sukses' => $request->session()->get('sukses'),
            'pilihanUrutan' => self::PILIHAN_URUT_POLI,
            'bawaanUrutan' => 'nama',
            'urutanAktif' => Urutan::kunci($request, self::PILIHAN_URUT_POLI, 'nama'),
            'arahUrutan' => Urutan::arah($request),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasiPoli($request);

        Poli::create($data);

        return redirect()->route('admin.poli')->with('sukses', 'Poli berhasil ditambahkan.');
    }

    public function ubah(Request $request, int $id): View
    {
        return view('admin.poli_ubah', [
            'poli' => Poli::findOrFail($id),
        ]);
    }

    public function perbarui(Request $request, int $id): RedirectResponse
    {
        $poli = Poli::findOrFail($id);
        $data = $this->validasiPoli($request, $poli->id);

        $poli->update($data);

        return redirect()->route('admin.poli')->with('sukses', 'Poli berhasil diperbarui.');
    }

    public function hapus(int $id): RedirectResponse
    {
        $poli = Poli::findOrFail($id);

        try {
            $poli->delete();
        } catch (QueryException) {
            // Relasi RESTRICT: poli dengan riwayat antrean di-nonaktifkan saja.
            $poli->update(['status_aktif' => false]);

            return redirect()->route('admin.poli')->with(
                'sukses',
                'Poli masih memiliki data antrean, sehingga hanya dinonaktifkan (tidak tampil bagi pasien).'
            );
        }

        return redirect()->route('admin.poli')->with('sukses', 'Poli berhasil dihapus.');
    }

    /* --------------------------- US-17: Jam operasional ------------------- */

    public function jam(Request $request): View
    {
        $arah = Urutan::arah($request);
        $urutan = Urutan::kunci($request, self::PILIHAN_URUT_JAM, 'hari');

        // Pengurutan dilakukan di PHP agar "hari" mengikuti urutan minggu
        // (Senin → Minggu), bukan urutan alfabetis.
        $kunci = match ($urutan) {
            'buka' => fn (JamOperasional $jam) => $jam->jam_buka,
            'tutup' => fn (JamOperasional $jam) => $jam->jam_tutup,
            default => fn (JamOperasional $jam) => array_search($jam->hari, JamOperasional::HARI, true),
        };

        $daftarPoli = Poli::with('jamOperasional')
            ->orderBy('nama_poli')
            ->get()
            ->each(function (Poli $poli) use ($kunci, $arah) {
                $poli->setRelation(
                    'jamOperasional',
                    $poli->jamOperasional->sortBy($kunci, SORT_STRING, $arah === 'desc')->values(),
                );
            });

        return view('admin.jam', [
            'daftarPoli' => $daftarPoli,
            'daftarHari' => JamOperasional::HARI,
            'opsiJam' => range(0, 23),
            'opsiMenit' => [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55],
            'pilihanUrutan' => self::PILIHAN_URUT_JAM,
            'bawaanUrutan' => 'hari',
            'urutanAktif' => $urutan,
            'arahUrutan' => $arah,
        ]);
    }

    /**
 * Jam buka dan tutup dipilih sebagai dua dropdown (jam 00–23 dan menit),
 * sehingga selalu tampil format 24 jam di peramban mana pun — berbeda
 * dengan <input type="time"> yang mengikuti locale browser (AM/PM).
 */
    public function simpanJam(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'poli_id' => ['required', 'exists:polis,id'],
            'hari' => ['required', 'in:' . implode(',', JamOperasional::HARI)],
            'jam_buka' => ['required', 'integer', 'between:0,23'],
            'menit_buka' => ['required', 'integer', 'between:0,59'],
            'jam_tutup' => ['required', 'integer', 'between:0,23'],
            'menit_tutup' => ['required', 'integer', 'between:0,59'],
        ], [], [
            'poli_id' => 'poli',
            'hari' => 'hari',
            'jam_buka' => 'jam buka',
            'menit_buka' => 'menit jam buka',
            'jam_tutup' => 'jam tutup',
            'menit_tutup' => 'menit jam tutup',
        ]);

        $buka = sprintf('%02d:%02d:00', $data['jam_buka'], $data['menit_buka']);
        $tutup = sprintf('%02d:%02d:00', $data['jam_tutup'], $data['menit_tutup']);

        if ($tutup <= $buka) {
            throw ValidationException::withMessages([
                'jam_tutup' => 'Jam tutup harus lebih besar daripada jam buka (format 24 jam).',
            ]);
        }

        JamOperasional::updateOrCreate(
            ['poli_id' => (int) $data['poli_id'], 'hari' => $data['hari']],
            ['jam_buka' => $buka, 'jam_tutup' => $tutup],
        );

        return redirect()->route('admin.jam')->with('sukses', 'Jam operasional disimpan.');
    }

    public function hapusJam(int $id): RedirectResponse
    {
        JamOperasional::findOrFail($id)->delete();

        return redirect()->route('admin.jam')->with('sukses', 'Jam operasional dihapus.');
    }

    /* ------------------------------ US-18: Kuota -------------------------- */

    public function kuota(Request $request): View
    {
        $daftarKuota = Urutan::terapkan(
            KuotaAntrean::with('poli')->where('tanggal_berlaku', '>=', Carbon::today()->toDateString()),
            $request,
            self::PILIHAN_URUT_KUOTA,
            'tanggal',
        )->get();

        return view('admin.kuota', [
            'daftarKuota' => $daftarKuota,
            'daftarPoli' => Poli::where('status_aktif', true)->orderBy('nama_poli')->get(),
            'tanggalHariIni' => Carbon::today()->toDateString(),
            'pilihanUrutan' => self::PILIHAN_URUT_KUOTA,
            'bawaanUrutan' => 'tanggal',
            'urutanAktif' => Urutan::kunci($request, self::PILIHAN_URUT_KUOTA, 'tanggal'),
            'arahUrutan' => Urutan::arah($request),
        ]);
    }

    public function simpanKuota(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'poli_id' => ['required', 'exists:polis,id'],
            'tanggal_berlaku' => ['required', 'date', 'after_or_equal:today'],
            'jumlah_kuota' => ['required', 'integer', 'min:1', 'max:1000'],
        ], [], [
            'poli_id' => 'poli',
            'tanggal_berlaku' => 'tanggal berlaku',
            'jumlah_kuota' => 'jumlah kuota',
        ]);

        KuotaAntrean::updateOrCreate(
            ['poli_id' => (int) $data['poli_id'], 'tanggal_berlaku' => $data['tanggal_berlaku']],
            ['jumlah_kuota' => (int) $data['jumlah_kuota']],
        );

        return redirect()->route('admin.kuota')->with('sukses', 'Kuota antrean harian disimpan.');
    }

    public function hapusKuota(int $id): RedirectResponse
    {
        KuotaAntrean::findOrFail($id)->delete();

        return redirect()->route('admin.kuota')->with('sukses', 'Kuota dihapus (poli kembali tanpa batas pada tanggal itu).');
    }

    /** @return array{kode_poli: string, nama_poli: string, deskripsi: ?string, status_aktif: bool} */
    private function validasiPoli(Request $request, ?int $kecuali = null): array
    {
        $data = $request->validate([
            'kode_poli' => [
                'required', 'string', 'max:16',
                Rule::unique('polis', 'kode_poli')->ignore($kecuali),
            ],
            'nama_poli' => [
                'required', 'string', 'max:100',
                Rule::unique('polis', 'nama_poli')->ignore($kecuali),
            ],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ], [], [
            'kode_poli' => 'kode poli',
            'nama_poli' => 'nama poli',
            'deskripsi' => 'deskripsi',
        ]);

        // Checkbox tidak terkirim bila tidak dicentang → nonaktif.
        $data['status_aktif'] = $request->boolean('status_aktif');

        return $data;
    }
}
