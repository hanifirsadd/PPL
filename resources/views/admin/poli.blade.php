@extends('layouts.app')
@section('judul', 'Kelola Poli — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Data Poli</h1>
        <p class="lembut">Kelola poli layanan yang tampil bagi pasien (US-16).</p>
    </div>
</div>

<div class="grid grid--dua">
    <div class="kartu">
        <h2 class="kartu__judul kartu__judul--kecil">Tambah Poli</h2>
        <form method="POST" action="{{ route('admin.poli.simpan') }}" class="form">
            @csrf
            <label class="form__baris">
                <span>Kode Poli</span>
                <input type="text" name="kode_poli" value="{{ old('kode_poli') }}" required maxlength="16" placeholder="PGT">
            </label>
            <label class="form__baris">
                <span>Nama Poli</span>
                <input type="text" name="nama_poli" value="{{ old('nama_poli') }}" required maxlength="100" placeholder="Poli Gig dan Mulut">
            </label>
            <label class="form__baris">
                <span>Deskripsi</span>
                <textarea name="deskripsi" rows="2" maxlength="500" placeholder="Deskripsi singkat layanan">{{ old('deskripsi') }}</textarea>
            </label>
            <label class="form__baris form__baris--centang">
                <input type="checkbox" name="status_aktif" value="1" checked>
                <span>Aktif (tampil bagi pasien)</span>
            </label>
            <button class="btn btn--utama" type="submit">Simpan Poli</button>
        </form>
    </div>

    <div class="kartu">
        <h2 class="kartu__judul kartu__judul--kecil">Daftar Poli</h2>
        <table class="tabel">
            <thead>
            <tr>
                @include('partials.urut', ['label' => 'Kode', 'kolom' => 'kode'])
                @include('partials.urut', ['label' => 'Nama', 'kolom' => 'nama'])
                @include('partials.urut', ['label' => 'Status', 'kolom' => 'status'])
                @include('partials.urut', ['label' => 'Antrean', 'kolom' => 'antrean'])
                <th class="tabel__aksi">Aksi</th>
            </tr>
            </thead>
            <tbody>
            @forelse($daftarPoli as $poli)
                <tr>
                    <td>{{ $poli->kode_poli }}</td>
                    <td>{{ $poli->nama_poli }}</td>
                    <td>
                        @if($poli->status_aktif)
                            <span class="badge badge--selesai">Aktif</span>
                        @else
                            <span class="badge badge--dilewati">Nonaktif</span>
                        @endif
                    </td>
                    <td>{{ $poli->antrean_count }}</td>
                    <td class="tabel__aksi">
                        <a class="btn btn--kecil btn--garis" href="{{ route('admin.poli.ubah', $poli->id) }}">Ubah</a>
                        <form method="POST" action="{{ route('admin.poli.hapus', $poli->id) }}"
                              onsubmit="return confirm('Hapus/nonaktifkan poli {{ $poli->nama_poli }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn--kecil btn--garis-merah" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="tabel--kosong">Belum ada poli.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
