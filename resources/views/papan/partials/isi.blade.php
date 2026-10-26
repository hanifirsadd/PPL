@php
    /** @var \Illuminate\Support\Collection $daftarPapan */
    $jamHariIni = now()->format('H:i');
    $namaHari = \Illuminate\Support\Str::title(strtolower(
        ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int) now()->format('w')]
    ));
@endphp

<div class="papan">
    @forelse($daftarPapan as $baris)
        @php($poli = $baris['poli'])
        <div class="kartu papan__poli {{ $baris['dilayani'] ? 'papan__poli--aktif' : '' }}">
            <div class="kartu__kepala">
                <span class="kode">{{ $poli->kode_poli }}</span>
                <h2 class="kartu__judul kartu__judul--kecil" style="margin:0">{{ $poli->nama_poli }}</h2>
            </div>

            <div class="papan__nomor">
                <span class="lembut">Sedang dilayani</span>
                <div class="papan__angka">{{ $baris['dilayani']?->nomor_antrean ?? '—' }}</div>
            </div>

            <div class="papan__dipanggil">
                <span class="lembut">Sedang dipanggil</span>
                <strong>{{ $baris['dipanggil']?->nomor_antrean ?? '— belum ada —' }}</strong>
            </div>

            <div class="papan__menunggu">
                <span class="lembut">
                    Antrean berikutnya
                    @if($baris['menunggu']->count() > 0)
                        ({{ $baris['menunggu']->count() }} orang menunggu)
                    @endif
                </span>
                <div class="papan__nomor-antrean">
                    @forelse($baris['menunggu']->take(6) as $antrean)
                        <span class="papan__chip">{{ $antrean->nomor_antrean }}</span>
                    @empty
                        <span class="lembut">Tidak ada antrean menunggu.</span>
                    @endforelse
                    @if($baris['menunggu']->count() > 6)
                        <span class="lembut">… dan {{ $baris['menunggu']->count() - 6 }} lainnya</span>
                    @endif
                </div>
            </div>

            <p class="lembut papan__jam">
                {{ $poli->sedangBuka() ? 'Buka' : 'Tutup' }} · {{ $namaHari }} {{ $jamHariIni }}
                @if(($sisa = $poli->sisaKuota()) !== null)
                    · sisa kuota {{ $sisa }}
                @endif
            </p>
        </div>
    @empty
        <div class="kartu kartu--tengah">
            <p class="lembut">Belum ada poli aktif.</p>
        </div>
    @endforelse
</div>