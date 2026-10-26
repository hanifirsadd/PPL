@extends('layouts.app')
@section('judul', 'Kelola Antrean — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Daftar Antrean Hari Ini</h1>
        <p class="lembut">{{ \Illuminate\Support\Carbon::today()->translatedFormat('d F Y') }} — panggil, layani, selesaikan, lewati, atau panggil ulang (US-10 s/d US-15).</p>
    </div>
    <div class="judul-halaman__aksi">
        <span class="lembut" data-segar="[daftar-antrean]">Diperbarui otomatis tiap 10 detik</span>
        <a class="btn btn--garis" href="{{ route('petugas.antrean', request()->query()) }}">⟳ Muat Ulang</a>
        <a class="btn btn--garis" href="{{ route('petugas.manual') }}">+ Input Manual (US-05)</a>
    </div>
</div>

<div class="kartu">
    <div class="filter">
        <form method="GET" action="{{ route('petugas.antrean') }}" class="filter__grup">
            <label class="filter__baris">
                <span>Poli</span>
                <select name="poli">
                    <option value="">Semua poli</option>
                    @foreach($daftarPoli as $poli)
                        <option value="{{ $poli->id }}" @selected($poliTerpilih === $poli->id)>{{ $poli->nama_poli }}</option>
                    @endforeach
                </select>
            </label>

            <label class="filter__baris">
                <span>Status</span>
                <select name="status">
                    <option value="">Semua status</option>
                    @foreach($semuaStatus as $status)
                        <option value="{{ $status->value }}" @selected($statusTerpilih === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>

            <button class="btn btn--garis" type="submit">Terapkan Filter</button>
        </form>

        {{-- Tombol panggil selalu tersedia: memakai poli yang sedang difilter,
             atau poli yang dipilih langsung di sini bila filter belum diisi. --}}
        <form method="POST" action="{{ route('petugas.panggil') }}" class="filter__panggil">
            @csrf
            @if($poliTerpilih)
                <input type="hidden" name="id_poli" value="{{ $poliTerpilih }}">
            @else
                <label class="filter__baris">
                    <span>Poli yang dipanggil</span>
                    <select name="id_poli" required>
                        <option value="">— pilih poli —</option>
                        @foreach($daftarPoli as $poli)
                            <option value="{{ $poli->id }}">{{ $poli->nama_poli }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="btn btn--utama" type="submit" @disabled($daftarPoli->isEmpty())>
                📞 Panggil Berikutnya (US-11)
            </button>
        </form>
    </div>
</div>

{{-- Isi tabel disegarkan otomatis lewat AJAX agar selalu sinkron dengan
     yang dilihat pasien, tanpa memuat ulang halaman. --}}
<div class="kartu" id="daftar-antrean">
    @include('petugas.partials.daftar')
</div>

@push('kepala')
<script>
    window.addEventListener('DOMContentLoaded', function () {
        window.antreanSegarkan('daftar-antrean', @json(route('petugas.antrean.fragmen', request()->query())), 10000);
    });
</script>
@endpush
@endsection