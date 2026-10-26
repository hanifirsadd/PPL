@extends('layouts.app')
@section('judul', 'Ubah Poli — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Ubah Poli</h1>
        <p class="lembut">Perbarui data {{ $poli->nama_poli }} (US-16).</p>
    </div>
    <a class="btn btn--garis" href="{{ route('admin.poli') }}">← Kembali</a>
</div>

<div class="kartu kartu--tengah">
    <form method="POST" action="{{ route('admin.poli.perbarui', $poli->id) }}" class="form">
        @csrf
        @method('PUT')

        <label class="form__baris">
            <span>Kode Poli</span>
            <input type="text" name="kode_poli" value="{{ old('kode_poli', $poli->kode_poli) }}" required maxlength="16">
        </label>
        <label class="form__baris">
            <span>Nama Poli</span>
            <input type="text" name="nama_poli" value="{{ old('nama_poli', $poli->nama_poli) }}" required maxlength="100">
        </label>
        <label class="form__baris">
            <span>Deskripsi</span>
            <textarea name="deskripsi" rows="2" maxlength="500">{{ old('deskripsi', $poli->deskripsi) }}</textarea>
        </label>
        <label class="form__baris form__baris--centang">
            <input type="checkbox" name="status_aktif" value="1" @checked(old('status_aktif', $poli->status_aktif))>
            <span>Aktif (tampil bagi pasien)</span>
        </label>

        <button class="btn btn--utama" type="submit">Simpan Perubahan</button>
    </form>
</div>
@endsection
