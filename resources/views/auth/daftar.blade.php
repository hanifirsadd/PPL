@extends('layouts.app')
@section('judul', 'Daftar — Antrean Puskesmas')

@section('isi')
<div class="kartu kartu--tengah">
    <h1 class="kartu__judul">Registrasi Pasien</h1>
    <p class="kartu__sub">Buat akun untuk mengambil nomor antrean secara online (US-01).</p>

    <form method="POST" action="{{ route('register.proses') }}" class="form">
        @csrf

        <label class="form__baris">
            <span>Nama Lengkap</span>
            <input type="text" name="nama" value="{{ old('nama') }}" required maxlength="120" placeholder="Nama Anda">
        </label>

        <label class="form__baris">
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="nama@contoh.test">
        </label>

        <label class="form__baris">
            <span>Kata Sandi <small>(min. 8 karakter)</small></span>
            <input type="password" name="password" required minlength="8">
        </label>

        <label class="form__baris">
            <span>Ulangi Kata Sandi</span>
            <input type="password" name="password_confirmation" required minlength="8">
        </label>

        <button class="btn btn--utama btn--lebar" type="submit">Buat Akun</button>
    </form>

    <p class="kartu__kaki">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
</div>
@endsection
