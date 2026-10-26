{{--
    Header tabel yang dapat diklik untuk mengurutkan.
    Pakai: @include('partials.urut', ['label' => 'Nomor', 'kolom' => 'nomor'])

    View pemanggil harus menyediakan:
      $pilihanUrutan : array kunci => kolom database
      $bawaanUrutan  : kunci pengurutan default
--}}
@php
    $aktif = \App\Support\Urutan::aktif(request(), $pilihanUrutan, $bawaanUrutan, $kolom);
    $arahBerikut = \App\Support\Urutan::arahBerikutnya(request(), $pilihanUrutan, $bawaanUrutan, $kolom);
    $arahSekarang = $aktif ? \App\Support\Urutan::arah(request()) : null;
@endphp
<th{{ $aktif ? ' class="urutan--aktif"' : '' }}>
    <a class="urutan" href="{{ \App\Support\Urutan::url(request(), $kolom, $arahBerikut) }}"
       title="Urutkan berdasarkan {{ strtolower($label) }}">
        {{ $label }}<span class="urutan__tanda">{{ $aktif ? ($arahSekarang === 'asc' ? '▲' : '▼') : '↕' }}</span>
    </a>
</th>