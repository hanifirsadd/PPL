@extends('layouts.app')
@section('judul', 'Pilih Poli — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Pilih Poli</h1>
        <p class="lembut">Ambil nomor antrean online sesuai poli yang Anda butuhkan (US-03, US-04).</p>
    </div>
    <div class="urutan-grup" role="group" aria-label="Urutkan daftar poli">
        <span class="lembut">Urutkan:</span>
        @foreach(['nama' => 'Nama poli', 'kode' => 'Kode poli', 'kuota' => 'Sisa kuota'] as $kunci => $label)
            @php($arahTujuan = \App\Support\Urutan::aktif(request(), $pilihanUrutan, $bawaanUrutan, $kunci) && $arahUrutan === 'asc' ? 'desc' : 'asc')
            <a class="urutan-tombol {{ $urutanAktif === $kunci ? 'aktif' : '' }}"
               href="{{ \App\Support\Urutan::url(request(), $kunci, $arahTujuan) }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

@if($antreanAktif)
    <div class="kartu kartu--sorot">
        <div>
            <strong>Anda sudah memiliki nomor antrean aktif hari ini.</strong>
            <p class="lembut" style="margin:.25rem 0 0">
                Nomor <span class="kartu__nomor">{{ $antreanAktif->nomor_antrean }}</span> —
                {{ $antreanAktif->poli->nama_poli }} ·
                @include('partials.badge', ['status' => $antreanAktif->status])
            </p>
        </div>
        <a class="btn btn--utama" href="{{ route('pasien.status') }}">Lihat Status Antrean</a>
    </div>
@endif

<div class="grid grid--kartu">
    @foreach($daftarPoli as $item)
        @php($poli = $item['poli'])
        <div class="kartu">
            <div class="kartu__kepala">
                <span class="kode">{{ $poli->kode_poli }}</span>
                @if($item['sedang_buka'])
                    <span class="badge badge--selesai">Buka</span>
                @else
                    <span class="badge badge--dilewati">Tutup</span>
                @endif
            </div>

            <h2 class="kartu__judul kartu__judul--kecil">{{ $poli->nama_poli }}</h2>
            <p class="lembut">{{ $poli->deskripsi ?: 'Poli layanan Puskesmas.' }}</p>

            <p class="kuota">
                @if($item['sisa_kuota'] === null)
                    Kuota: <strong>tidak dibatasi</strong>
                @elseif($item['sisa_kuota'] > 0)
                    Sisa kuota hari ini: <strong>{{ $item['sisa_kuota'] }}</strong>
                @else
                    Kuota hari ini: <strong>habis</strong>
                @endif
            </p>

            <form method="POST" action="{{ route('pasien.ambil') }}">
                @csrf
                <input type="hidden" name="id_poli" value="{{ $poli->id }}">
                <button class="btn btn--utama btn--lebar" type="submit"
                    @disabled(!$item['sedang_buka'] || ($item['sisa_kuota'] !== null && $item['sisa_kuota'] <= 0) || $antreanAktif !== null)>
                    {{ $antreanAktif ? 'Sudah ambil antrean' : 'Ambil Antrean' }}
                </button>
            </form>
        </div>
    @endforeach

    @if($daftarPoli->isEmpty())
        <div class="kartu"><p class="lembut">Belum ada poli aktif. Silakan hubungi petugas puskesmas.</p></div>
    @endif
</div>
@endsection
