@extends('layouts.app')
@section('judul', 'Papan Antrean — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Papan Antrean</h1>
        <p class="lembut">
            {{\Illuminate\Support\Carbon::today()->translatedFormat('d F Y') }} —
            {{ $jumlahMenunggu }} orang menunggu di seluruh poli.
        </p>
    </div>
    <div class="judul-halaman__aksi">
        <span class="lembut" data-segar="[papan-antrean]">Diperbarui otomatis tiap 10 detik</span>
        @guest
            <a class="btn btn--utama" href="{{ route('login') }}">Masuk untuk Ambil Antrean</a>
        @endguest
    </div>
</div>

<div class="pemberitahuan pemberitahuan--info">
    Halaman ini dapat dilihat tanpa akun. Untuk mengambil nomor antrean dan
    memantau posisi, silakan masuk atau daftar sebagai pasien.
</div>

{{-- Diperbarui otomatis lewat AJAX agar data tetap sama dengan yang dilihat petugas. --}}
<div id="papan-antrean">
    @include('papan.partials.isi')
</div>

@push('kepala')
<script>
    window.addEventListener('DOMContentLoaded', function () {
        window.antreanSegarkan('papan-antrean', @json(route('papan.segarkan')), 10000);
    });
</script>
@endpush
@endsection