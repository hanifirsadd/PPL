{{-- Fragmen tabel antrean — dimuat penuh oleh halaman dan disegarkan
     otomatis lewat AJAX agar data selalu sama dengan yang dilihat pasien. --}}
<table class="tabel">
    <thead>
    <tr>
        @include('partials.urut', ['label' => 'Nomor', 'kolom' => 'nomor'])
        <th>Poli</th>
        @include('partials.urut', ['label' => 'Pasien', 'kolom' => 'pasien'])
        <th>Sumber</th>
        @include('partials.urut', ['label' => 'Status', 'kolom' => 'status'])
        @include('partials.urut', ['label' => 'Waktu dipanggil', 'kolom' => 'waktu'])
        <th class="tabel__aksi">Aksi</th>
    </tr>
    </thead>
    <tbody>
    @forelse($daftar as $antrean)
        <tr>
            <td><strong>{{ $antrean->nomor_antrean }}</strong></td>
            <td>{{ $antrean->poli->nama_poli }}</td>
            <td>
                {{ $antrean->namaPasien() }}
                @if($antrean->user_id === null && $antrean->nama_pasien_manual)
                    <span class="lembut"> · tanpa akun</span>
                @endif
            </td>
            <td>{{ $antrean->tipe->label() }}</td>
            <td>@include('partials.badge', ['status' => $antrean->status])</td>
            <td class="lembut">
                @if($antrean->waktu_dipanggil)
                    dipanggil {{ $antrean->waktu_dipanggil->format('H:i') }}<br>
                @endif
                @if($antrean->waktu_mulai)
                    mulai {{ $antrean->waktu_mulai->format('H:i') }}<br>
                @endif
                @if($antrean->waktu_selesai)
                    selesai {{ $antrean->waktu_selesai->format('H:i') }}
                @endif
                @if(!$antrean->waktu_dipanggil && !$antrean->waktu_mulai && !$antrean->waktu_selesai)
                    —
                @endif
            </td>
            <td class="tabel__aksi">
                @switch($antrean->status->value)
                    @case('MENUNGGU')
                        <form method="POST" action="{{ route('petugas.panggil.spesifik', $antrean->id) }}">
                            @csrf
                            <button class="btn btn--kecil btn--utama" type="submit">📞 Panggil {{ $antrean->nomor_antrean }} (US-11)</button>
                        </form>
                        @break
                    @case('DIPANGGIL')
                        <form method="POST" action="{{ route('petugas.mulai', $antrean->id) }}">
                            @csrf
                            <button class="btn btn--kecil btn--utama" type="submit">Mulai Layani (US-12)</button>
                        </form>
                        <form method="POST" action="{{ route('petugas.lewati', $antrean->id) }}">
                            @csrf
                            <button class="btn btn--kecil btn--garis-merah" type="submit">Lewati (US-14)</button>
                        </form>
                        @break
                    @case('DILAYANI')
                        <form method="POST" action="{{ route('petugas.selesai', $antrean->id) }}">
                            @csrf
                            <button class="btn btn--kecil btn--hijau" type="submit">Selesai (US-13)</button>
                        </form>
                        @break
                    @case('DILEWATI')
                        <form method="POST" action="{{ route('petugas.panggil-ulang', $antrean->id) }}">
                            @csrf
                            <button class="btn btn--kecil btn--utama" type="submit">Panggil Ulang (US-15)</button>
                        </form>
                        @break
                    @default
                        <span class="lembut">—</span>
                @endswitch
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="tabel--kosong">Belum ada antrean hari ini.</td>
        </tr>
    @endforelse
    </tbody>
</table>