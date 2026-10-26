@extends('layouts.app')
@section('judul', 'Jam Operasional — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Jam Operasional</h1>
        <p class="lembut">Atur jam buka–tutup pelayanan tiap poli; menentukan kapan pasien boleh mengambil antrean (US-17).</p>
    </div>
    <div class="urutan-grup" role="group" aria-label="Urutkan jadwal">
        @foreach(['hari' => 'Hari', 'buka' => 'Jam buka', 'tutup' => 'Jam tutup'] as $kunci => $label)
            @php($arahTujuan = \App\Support\Urutan::aktif(request(), $pilihanUrutan, $bawaanUrutan, $kunci) && $arahUrutan === 'asc' ? 'desc' : 'asc')
            <a class="urutan-tombol {{ $urutanAktif === $kunci ? 'aktif' : '' }}"
               href="{{ \App\Support\Urutan::url(request(), $kunci, $arahTujuan) }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="kartu">
    <h2 class="kartu__judul kartu__judul--kecil">Tambah / Ubah Jadwal</h2>
    <form method="POST" action="{{ route('admin.jam.simpan') }}" class="filter">
        @csrf
        <label class="filter__baris">
            <span>Poli</span>
            <select name="poli_id" required>
                <option value="">— pilih poli —</option>
                @foreach($daftarPoli as $poli)
                    <option value="{{ $poli->id }}" @selected(old('poli_id') == $poli->id)>{{ $poli->nama_poli }}</option>
                @endforeach
            </select>
        </label>
        <label class="filter__baris">
            <span>Hari</span>
            <select name="hari" required>
                @foreach($daftarHari as $hari)
                    <option value="{{ $hari }}" @selected(old('hari') === $hari)>{{ \Illuminate\Support\Str::title(strtolower($hari)) }}</option>
                @endforeach
            </select>
        </label>
        <label class="filter__baris">
            <span>Jam Buka <small>(24 jam)</small></span>
            <span class="pilih-waktu">
                <select name="jam_buka" required>
                    @foreach($opsiJam as $jam)
                        <option value="{{ $jam }}" @selected((int) old('jam_buka', 8) === $jam)>
                            {{ str_pad($jam, 2, '0', STR_PAD_LEFT) }}
                        </option>
                    @endforeach
                </select>
                <span class="pilih-waktu__titik">:</span>
                <select name="menit_buka" required>
                    @foreach($opsiMenit as $menit)
                        <option value="{{ $menit }}" @selected((int) old('menit_buka', 0) === $menit)>
                            {{ str_pad($menit, 2, '0', STR_PAD_LEFT) }}
                        </option>
                    @endforeach
                </select>
            </span>
        </label>
        <label class="filter__baris">
            <span>Jam Tutup <small>(24 jam)</small></span>
            <span class="pilih-waktu">
                <select name="jam_tutup" required>
                    @foreach($opsiJam as $jam)
                        <option value="{{ $jam }}" @selected((int) old('jam_tutup', 14) === $jam)>
                            {{ str_pad($jam, 2, '0', STR_PAD_LEFT) }}
                        </option>
                    @endforeach
                </select>
                <span class="pilih-waktu__titik">:</span>
                <select name="menit_tutup" required>
                    @foreach($opsiMenit as $menit)
                        <option value="{{ $menit }}" @selected((int) old('menit_tutup', 0) === $menit)>
                            {{ str_pad($menit, 2, '0', STR_PAD_LEFT) }}
                        </option>
                    @endforeach
                </select>
            </span>
        </label>
        <button class="btn btn--utama" type="submit">Simpan Jadwal</button>
    </form>
</div>

<div class="grid grid--kartu">
    @foreach($daftarPoli as $poli)
        <div class="kartu">
            <div class="kartu__kepala">
                <h2 class="kartu__judul kartu__judul--kecil" style="margin:0">{{ $poli->nama_poli }}</h2>
                @if(!$poli->status_aktif)<span class="badge badge--dilewati">Nonaktif</span>@endif
            </div>
            <table class="tabel">
                <thead>
                <tr>
                    @include('partials.urut', ['label' => 'Hari', 'kolom' => 'hari'])
                    <th>Jam</th>
                    <th class="tabel__aksi"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($poli->jamOperasional->sortBy('hari') as $jam)
                    <tr>
                        <td>{{ \Illuminate\Support\Str::title(strtolower($jam->hari)) }}</td>
                        <td>{{ substr($jam->jam_buka, 0, 5) }} – {{ substr($jam->jam_tutup, 0, 5) }}</td>
                        <td class="tabel__aksi">
                            <form method="POST" action="{{ route('admin.jam.hapus', $jam->id) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn--kecil btn--garis-merah" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="tabel--kosong">Belum ada jadwal — poli dianggap tutup.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection
