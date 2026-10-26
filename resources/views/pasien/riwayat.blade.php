@extends('layouts.app')
@section('judul', 'Riwayat Antrean — Antrean Puskesmas')

@section('isi')
<div class="judul-halaman">
    <div>
        <h1>Riwayat Antrean</h1>
        <p class="lembut">Seluruh nomor antrean yang pernah Anda ambil.</p>
    </div>
</div>

<div class="kartu">
    <table class="tabel">
        <thead>
        <tr>
            @include('partials.urut', ['label' => 'Tanggal', 'kolom' => 'tanggal'])
            @include('partials.urut', ['label' => 'Nomor', 'kolom' => 'nomor'])
            @include('partials.urut', ['label' => 'Poli', 'kolom' => 'poli'])
            <th>Sumber</th>
            @include('partials.urut', ['label' => 'Status', 'kolom' => 'status'])
        </tr>
        </thead>
        <tbody>
        @forelse($riwayat as $baris)
            <tr>
                <td>{{ $baris->tanggal_antrean->translatedFormat('d/m/Y') }}</td>
                <td><strong>{{ $baris->nomor_antrean }}</strong></td>
                <td>{{ $baris->poli->nama_poli }}</td>
                <td>{{ $baris->tipe->label() }}</td>
                <td>@include('partials.badge', ['status' => $baris->status])</td>
            </tr>
        @empty
            <tr><td colspan="5" class="tabel--kosong">Belum ada riwayat antrean.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
