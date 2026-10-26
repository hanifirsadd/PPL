<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Sistem Manajemen Antrean Puskesmas')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('kepala')
</head>
<body>
<header class="bar">
    <div class="bar__kiri">
        <a class="bar__logo" href="{{ route('beranda') }}">🩺 Antrean Puskesmas</a>
        @auth
            <nav class="bar__nav">
                @if(auth()->user()->isPasien())
                    <a class="{{ request()->routeIs('pasien.poli') ? 'aktif' : '' }}" href="{{ route('pasien.poli') }}">Ambil Antrean</a>
                    <a class="{{ request()->routeIs('pasien.status') ? 'aktif' : '' }}" href="{{ route('pasien.status') }}">Status Antrean</a>
                    <a class="{{ request()->routeIs('pasien.riwayat') ? 'aktif' : '' }}" href="{{ route('pasien.riwayat') }}">Riwayat</a>
                    <a class="{{ request()->routeIs('pasien.notifikasi') ? 'aktif' : '' }}" href="{{ route('pasien.notifikasi') }}">
                        Notifikasi
                        @php($belumDibaca = \App\Models\Notifikasi::where('user_id', auth()->id())->whereNull('dibaca_pada')->count())
                        @if($belumDibaca > 0)<span class="bar__titik">{{ $belumDibaca }}</span>@endif
                    </a>
                @else
                    <a class="{{ request()->routeIs('petugas.antrean') ? 'aktif' : '' }}" href="{{ route('petugas.antrean') }}">Daftar Antrean</a>
                    <a class="{{ request()->routeIs('petugas.manual') ? 'aktif' : '' }}" href="{{ route('petugas.manual') }}">Input Manual</a>
                    @if(auth()->user()->isAdmin())
                        <a class="{{ request()->routeIs('admin.poli*') ? 'aktif' : '' }}" href="{{ route('admin.poli') }}">Poli</a>
                        <a class="{{ request()->routeIs('admin.jam*') ? 'aktif' : '' }}" href="{{ route('admin.jam') }}">Jam Operasional</a>
                        <a class="{{ request()->routeIs('admin.kuota*') ? 'aktif' : '' }}" href="{{ route('admin.kuota') }}">Kuota</a>
                    @endif
                @endif
            </nav>
        @endauth
    </div>

    <div class="bar__kanan">
        @auth
            <span class="bar__pengguna">
                {{ auth()->user()->nama }}
                <span class="badge badge--peran badge--{{ strtolower(auth()->user()->peran) }}">{{ auth()->user()->peran }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn--kecil btn--garis" type="submit">Keluar</button>
            </form>
        @else
            <nav class="bar__nav">
                <a class="{{ request()->routeIs('papan*') ? 'aktif' : '' }}" href="{{ route('papan') }}">Papan Antrean</a>
            </nav>
            <a class="btn btn--kecil" href="{{ route('login') }}">Masuk</a>
        @endauth
    </div>
</header>

<main class="kontainer">
    @if(session('sukses'))
        <div class="pemberitahuan pemberitahuan--sukses" role="status">{{ session('sukses') }}</div>
    @endif

    @if($errors->any())
        <div class="pemberitahuan pemberitahuan--gagal" role="alert">
            <ul>
                @foreach($errors->all() as $galat)
                    <li>{{ $galat }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('isi')
</main>

<footer class="kaki">
    Sistem Manajemen Antrean Puskesmas Berbasis Web — Pengelolaan antrean & informasi (bukan layanan medis).
</footer>
</body>
</html>
