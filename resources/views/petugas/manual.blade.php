@extends('layouts.app')
@section('judul', 'Input Antrean Manual — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Input Antrean Manual</h1>
        <p class="lembut">Catat pasien datang langsung (walk-in) ke daftar antrean yang sama dengan antrean online (US-05).</p>
    </div>
    <a class="btn btn--garis" href="{{ route('petugas.antrean') }}">← Kembali ke Daftar</a>
</div>

<div class="kartu kartu--tengah">
    <form method="POST" action="{{ route('petugas.manual.simpan') }}" class="form">
        @csrf

        <label class="form__baris">
            <span>Poli</span>
            <select name="id_poli" required>
                <option value="">— pilih poli —</option>
                @foreach($daftarPoli as $poli)
                    <option value="{{ $poli->id }}" @selected(old('id_poli') == $poli->id)>
                        {{ $poli->nama_poli }} ({{ $poli->kode_poli }})
                    </option>
                @endforeach
            </select>
        </label>

        <label class="form__baris">
            <span>Nama Pasien <small>(wajib bila pasien tanpa akun)</small></span>
            <input type="text" name="nama" value="{{ old('nama') }}" maxlength="120" placeholder="Nama lengkap pasien">
        </label>

        <label class="form__baris">
            <span>Email Pasien <small>(opsional — isi bila pasien sudah punya akun)</small></span>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="pasien@contoh.test">
        </label>

        <p class="lembut">
            Kosongkan email untuk pasien tanpa akun (identitas dicatat sebagai antrean manual).
            Isi email bila pasien terdaftar agar aturan "maksimal satu antrean aktif per poli per hari" ikut berlaku (KNF-01).
        </p>

        <button class="btn btn--utama btn--lebar" type="submit">Buat Nomor Antrean</button>
    </form>
</div>
@endsection
