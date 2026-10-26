@extends('layouts.app')
@section('judul', 'Masuk — Antrean Puskesmas')

@section('isi')
<div class="kartu kartu--tengah">
    <h1 class="kartu__judul">Masuk</h1>
    <p class="kartu__sub">Gunakan akun Anda untuk mengakses sistem antrean.</p>

    <form method="POST" action="{{ route('login.proses') }}" class="form">
        @csrf

        <label class="form__baris">
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="nama@contoh.test">
        </label>

        <label class="form__baris">
            <span>Kata Sandi</span>
            <input type="password" name="password" required placeholder="••••••••">
        </label>

        <label class="form__baris form__baris--centang">
            <input type="checkbox" name="ingat" value="1">
            <span>Ingat saya</span>
        </label>

        <button class="btn btn--utama btn--lebar" type="submit">Masuk</button>

        <p class="form__pisah"><span>atau</span></p>

        {{-- Tamu: hanya melihat papan antrean, tanpa perlu akun. --}}
        <a class="btn btn--garis btn--lebar" id="tombol-tamu" href="{{ route('papan') }}">
            👁 Masuk sebagai Tamu
        </a>
        <p class="lembut" style="text-align:center">
            Melihat papan antrean saja — tidak dapat mengambil nomor antrean.
        </p>
    </form>

    <p class="kartu__kaki">Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai pasien</a></p>
</div>
@endsection
