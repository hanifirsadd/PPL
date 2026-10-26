@extends('layouts.app')
@section('judul', 'Kuota Antrean — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Kuota Antrean Harian</h1>
        <p class="lembut">Tentukan batas maksimal antrean per poli tiap hari (US-18).</p>
    </div>
</div>

<div class="kartu">
    <h2 class="kartu__judul kartu__judul--kecil">Tetapkan Kuota</h2>
    <form method="POST" action="{{ route('admin.kuota.simpan') }}" class="filter">
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
            <span>Tanggal</span>
            <input type="date" name="tanggal_berlaku" value="{{ old('tanggal_berlaku', $tanggalHariIni) }}" min="{{ $tanggalHariIni }}" required>
        </label>
        <label class="filter__baris">
            <span>Jumlah Maksimal</span>
            <input type="number" name="jumlah_kuota" value="{{ old('jumlah_kuota', 30) }}" min="1" max="1000" required>
        </label>
        <button class="btn btn--utama" type="submit">Simpan Kuota</button>
    </form>
</div>

<div class="kartu">
    <h2 class="kartu__judul kartu__judul--kecil">Kuota Mendatang</h2>
    <table class="tabel">
        <thead>
        <tr>
            @include('partials.urut', ['label' => 'Tanggal', 'kolom' => 'tanggal'])
            @include('partials.urut', ['label' => 'Poli', 'kolom' => 'poli'])
            @include('partials.urut', ['label' => 'Kuota', 'kolom' => 'jumlah'])
            <th class="tabel__aksi">Aksi</th>
        </tr>
        </thead>
        <tbody>
        @forelse($daftarKuota as $kuota)
            <tr>
                <td>{{ $kuota->tanggal_berlaku->translatedFormat('d/m/Y') }}</td>
                <td>{{ $kuota->poli->nama_poli }}</td>
                <td>{{ $kuota->jumlah_kuota }} pasien</td>
                <td class="tabel__aksi">
                    <form method="POST" action="{{ route('admin.kuota.hapus', $kuota->id) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn--kecil btn--garis-merah" type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="tabel--kosong">Belum ada kuota ditetapkan (poli tanpa batas).</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
