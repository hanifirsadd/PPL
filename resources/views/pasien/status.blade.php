@extends('layouts.app')
@section('judul', 'Status Antrean — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Status Antrean Saya</h1>
        <p class="lembut">Posisi dan status diperbarui otomatis setiap 10 detik (US-06, US-07).</p>
    </div>
    <span class="lembut" data-segar="[status-antrean]">Diperbarui otomatis tiap 10 detik</span>
</div>

<div id="status-antrean">
    @if($antrean === null)
        @include('pasien.partials.kosong')
    @else
        @include('pasien.partials.status')
    @endif
</div>

<div class="kartu">
    <h2 class="kartu__judul kartu__judul--kecil">Keterangan</h2>
    <ul class="daftar-ket">
        <li><span class="badge badge--menunggu">Menunggu</span> nomor Anda menunggu giliran.</li>
        <li><span class="badge badge--dipanggil">Dipanggil</span> segera menuju loket — Anda menerima notifikasi.</li>
        <li><span class="badge badge--dilayani">Dilayani</span> sedang dilayani petugas.</li>
        <li><span class="badge badge--selesai">Selesai</span> pelayanan selesai.</li>
        <li><span class="badge badge--dilewati">Dilewati</span> tidak hadir saat dipanggil; hubungi petugas untuk dipanggil ulang.</li>
    </ul>
    <p class="lembut">
        Anda juga menerima notifikasi otomatis saat tersisa {{ (int) config('antrean.reminder_sisa') }} antrean sebelum giliran Anda (US-08).
        Catatan: nomor antrean berlaku di dalam poli masing-masing, sehingga nomor yang sama dapat muncul pada poli berbeda.
    </p>
</div>

@push('kepala')
<script>
    window.addEventListener('DOMContentLoaded', function () {
        window.antreanSegarkan('status-antrean', @json(route('pasien.status.segarkan')), 10000);
    });
</script>
@endpush
@endsection