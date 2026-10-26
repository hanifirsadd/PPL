@extends('layouts.app')
@section('judul', 'Notifikasi — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Notifikasi Antrean</h1>
        <p class="lembut">Pengingat "tersisa 3 antrean" (US-08) dan panggilan nomor Anda (US-09).</p>
    </div>
    <div class="judul-halaman__aksi">
        <div class="urutan-grup" role="group" aria-label="Urutkan notifikasi">
            @foreach(['waktu' => 'Terbaru', 'jenis' => 'Jenis', 'antrean' => 'Nomor antrean'] as $kunci => $label)
                @php($arahTujuan = \App\Support\Urutan::aktif(request(), $pilihanUrutan, $bawaanUrutan, $kunci) && $arahUrutan === 'desc' ? 'asc' : 'desc')
                <a class="urutan-tombol {{ $urutanAktif === $kunci ? 'aktif' : '' }}"
                   href="{{ \App\Support\Urutan::url(request(), $kunci, $arahTujuan) }}">{{ $label }}</a>
            @endforeach
        </div>
        @if($daftar->isNotEmpty())
            <form method="POST" action="{{ route('pasien.notifikasi.baca-semua') }}">
                @csrf
                <button class="btn btn--garis" type="submit">Tandai semua dibaca</button>
            </form>
        @endif
    </div>
</div>

<div class="daftar-notifikasi">
    @forelse($daftar as $notifikasi)
        <div class="kartu notifikasi {{ $notifikasi->sudahDibaca() ? '' : 'notifikasi--baru' }}">
            <div class="notifikasi__kepala">
                <span class="badge badge--{{ $notifikasi->jenis === \App\Models\Notifikasi::REMINDER_TIGA ? 'dipanggil' : 'dilayani' }}">
                    {{ $notifikasi->jenis === \App\Models\Notifikasi::REMINDER_TIGA ? 'Pengingat' : 'Panggilan' }}
                </span>
                <span class="lembut">{{ $notifikasi->created_at->diffForHumans() }}</span>
            </div>
            <p>{{ $notifikasi->pesan }}</p>
            <div class="notifikasi__kaki">
                <span class="lembut">
                    {{ $notifikasi->antrean?->nomor_antrean }} · {{ $notifikasi->antrean?->poli?->nama_poli }}
                </span>
                @unless($notifikasi->sudahDibaca())
                    <form method="POST" action="{{ route('pasien.notifikasi.baca', $notifikasi->id) }}">
                        @csrf
                        <button class="btn btn--kecil btn--garis" type="submit">Tandai dibaca</button>
                    </form>
                @endunless
            </div>
        </div>
    @empty
        <div class="kartu kartu--tengah">
            <p class="lembut">Belum ada notifikasi. Notifikasi muncul saat tersisa {{ (int) config('antrean.reminder_sisa') }} antrean dan saat nomor Anda dipanggil.</p>
        </div>
    @endforelse
</div>
@endsection
