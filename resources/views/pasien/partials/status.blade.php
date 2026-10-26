<div class="grid grid--status">
    <div class="kartu kartu--nomor">
        <span class="lembut">Nomor Antrean Anda</span>
        <div class="kartu__nomor besar">{{ $antrean->nomor_antrean }}</div>
        <p class="lembut">{{ $antrean->poli->nama_poli }} · {{ $antrean->tanggal_antrean->translatedFormat('d F Y') }}</p>
        <div style="margin-top:.75rem">
            @include('partials.badge', ['status' => $antrean->status])
        </div>
    </div>

    <div class="kartu">
        <div class="angka">
            <span class="angka__nilai">{{ $sisa_di_depan }}</span>
            <span class="angka__label">Antrean di depan Anda</span>
        </div>
        <div class="angka">
            <span class="angka__nilai">{{ $nomor_sedang_dilayani ?? '—' }}</span>
            <span class="angka__label">Sedang dilayani</span>
        </div>
        <div class="angka">
            <span class="angka__nilai">{{ $jumlah_total }}</span>
            <span class="angka__label">Total antrean poli hari ini</span>
        </div>
    </div>
</div>