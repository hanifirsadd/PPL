# Sistem Manajemen Antrean Puskesmas Berbasis Web

Aplikasi web untuk mengelola antrean (**antrean**, bukan layanan medis) di Puskesmas:
pasien mengambil nomor antrean secara online, petugas memanggil serta memantau
antrean, dan admin mengelola poli, jam operasional, serta kuota harian.

Dibangun mengikuti proposal: **PHP + Laravel (KT-01)**, pengujian otomatis
**PHPUnit + Laravel Dusk (KT-02)**, dan basis data **relasional MySQL (KT-03)**.

---

## 1. Kebutuhan Sistem

| Komponen | Versi / Catatan |
|---|---|
| PHP | 8.1+ (di bundle XAMPP, `C:\xampp\php`) |
| Composer | 2.x (terpasang di `C:\composer`) |
| MySQL / MariaDB | XAMPP (port 3306, user `root`, tanpa kata sandi) |
| Chrome / Edge | Untuk pengujian browser (Laravel Dusk) |

> Di lingkungan ini XAMPP berisi MariaDB 10.4, bukan MySQL 8. Keduanya kompatibel
> dengan skema yang dipakai (lihat catatan pada bagian 6).

## 2. Cara Menjalankan (Windows)

```bat
scripts\jalankan.cmd
```

Skrip tersebut menyalakan MySQL bila belum berjalan, menyiapkan basis data,
memuat data contoh, lalu membuka server di <http://127.0.0.1:8000>.

Manual bila perlu:

```bat
set PATH=C:\xampp\php;%PATH%
composer install
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

### Akun contoh

Sudah dibuat oleh seeder, kata sandi semuanya `password123`:

| Email | Peran |
|---|---|
| `admin@puskesmas.test` | Admin |
| `petugas@puskesmas.test` | Petugas |
| `budi@contoh.test` | Pasien |

## 2b. Papan Antrean untuk Tamu

Halaman `/papan` dapat dibuka **tanpa login** (tombol *Masuk sebagai Tamu* di
halaman masuk). Tamu hanya melihat nomor antrean yang sedang dilayani, sedang
dipanggil, dan antrean berikutnya per poli — tanpa identitas pasien, dan tetap
tidak dapat mengambil nomor antrean.

## 3. Pengujian Otomatis (KNF-03 / KT-02)

```bat
scripts\uji.cmd
```

atau terpisah:

```bat
php artisan test          :: 95 test aturan bisnis & antarmuka (PHPUnit)
php artisan dusk          :: 18 test browser sungguhan (Chrome)
```

Pengujian browser memakai basis data terpisah (`antrean_puskesmas_dusk`)
dan membutuhkan server yang berjalan dengan environment Dusk. Siapkan
konfigurasinya sekali:

```bat
copy .env.dusk.example .env.dusk.local
php artisan key:generate --env=dusk.local
```

Lalu jalankan server:

```bat
php artisan serve --env=dusk.local --host=127.0.0.1 --port=8000
```

Ringkasan cakupan pengujian:

| Berkas test | Cakupan |
|---|---|
| `tests/Unit/StatusAntreanTest.php` | State machine status antrean |
| `tests/Feature/AuthTest.php` | US-01, US-02, KNF-02 (hash & otorisasi peran) |
| `tests/Feature/AmbilAntreanTest.php` | US-03, US-04, US-06, US-07, KNF-01, kuota |
| `tests/Feature/AntreanManualTest.php` | US-05 (walk-in, `Nama_pasien_manual`, `Dibuat_oleh`) |
| `tests/Feature/PengelolaanAntreanTest.php` | US-10 s/d US-15 (alur normal & cabang lewati) |
| `tests/Feature/NotifikasiTest.php` | US-08, US-09 (reminder sekali kirim, pemanggilan) |
| `tests/Feature/AdminTest.php` | US-16, US-17, US-18 |
| `tests/Feature/KonstrainDatabaseTest.php` | KNF-01 & integritas di level database |
| `tests/Feature/SortingDaftarTest.php` | Pengurutan seluruh daftar + penolakan kolom tak dikenal |
| `tests/Browser/*DuskTest.php` | Alur end-to-end untuk Pasien, Petugas, dan Admin |

## 4. Struktur Proyek

```
app/
  Enums/StatusAntrean.php        enumerasi status + aturan transisi (class diagram)
  Enums/TipeAntrean.php          ONLINE / MANUAL
  Exceptions/AturanBisnisException.php
  Http/Controllers/
    AuthController.php           US-01, US-02
    AntreanPasienController.php  US-03, US-04, US-06 s/d US-09
    AntreanPetugasController.php US-05, US-10 s/d US-15
    PoliAdminController.php      US-16, US-17, US-18
  Http/Middleware/EnsurePeran.php  otorisasi berbasis peran (KNF-02)
  Models/                        User, Poli, JamOperasional, KuotaAntrean, Antrean, Notifikasi
  Services/
    AntreanService.php           seluruh aturan antrean
    NotifikasiService.php        reminder & pemanggilan
resources/views/                 Blade: auth, pasien, petugas, admin
database/migrations/             skema sesuai ERD proposal
routes/web.php                   rute per peran
tests/                           PHPUnit + Dusk
```

## 5. Basis Data (mengikuti ERD proposal)

| Tabel | Isi | Relasi |
|---|---|---|
| `users` | akun + `peran` (PASIEN/PETUGAS/ADMIN) | 1—N `antrean` |
| `polis` | kode unik, nama, status aktif | 1—N jam operasional, kuota, antrean |
| `jam_operasional` | hari, jam buka–tutup per poli | banyak ke 1 `polis` |
| `kuota_antrean` | jumlah maksimum per tanggal | banyak ke 1 `polis` |
| `antrean` | nomor, tipe, status, waktu, identitas | banyak ke 1 `polis` & `users` |
| `notifikasi` | jenis, pesan, sudah dibaca | banyak ke 1 `antrean` & `users` |

Aturan penting yang ditegakkan **di level database**:

- **KNF-01** — satu antrean **aktif** (MENUNGGU/DIPANGGIL/DILAYANI) per pasien
  per poli per hari, melalui *generated column* `kunci_aktif` + unique index.
- Satu pasien **dilayani** per poli per hari (`kunci_dilayani`).
- Nomor antrean unik per poli per hari (antrean online dan manual berbagi urutan).
- Konsistensi stempel waktu terhadap status, dan identitas pasien selalu ada
  (`user_id` **atau** `nama_pasien_manual`).

## 6. Catatan Teknis

- **Nomor antrean** memakai awalan kode poli: `PU-0001` (Poli Umum), `PGT-0002`
  (Poli Gigi), … per poli per hari, dibagikan antara pendaftaran online dan input
  manual. Nomor yang sama tidak pernah muncul pada dua poli berbeda, sehingga
  tidak pernah tertukar antara tampilan pasien dan petugas.
- **Penyegaran otomatis**: daftar antrean (petugas) dan status antrean (pasien)
  disegarkan tiap 10 detik lewat AJAX, jadi keduanya selalu menampilkan data
  yang sama tanpa perlu memuat ulang halaman.
- **Pengurutan daftar**: seluruh daftar (poli, antrean, riwayat, notifikasi, jam
  operasional, kuota) dapat diurutkan lewat judul kolom atau tombol *Urutkan*,
  dengan arah naik/turun yang dapat ditukar dan tanpa menghilangkan filter aktif.
  Kolom yang boleh diurutkan dibatasi daftar putih (`App\Support\Urutan`), sehingga
  parameter URL tidak dapat menyuntikkan kolom sembarang.
- **Status**: MENUNGGU → DIPANGGIL → DILAYANI → SELESAI, dengan cabang
  DIPANGGIL → DILEWATI → DIPANGGIL. Transisi ilegal ditolak.
- **Notifikasi**: dikirim saat tersisa 1–3 antrean di depan pasien
  (`REMINDER_3_ANTREAN`) dan saat nomor dipanggil (`DIPANGGIL`).
  Constraint `UNIQUE (antrean_id, jenis)` membuat pengingat tidak pernah dobel.
  Ambang dapat diubah lewat `REMINDER_SISA` di `.env`.
- **Tanpa akun**: pasien yang datang langsung dicatat manual
  (`nama_pasien_manual`, `dibuat_oleh`), sehingga tidak memiliki kotak notifikasi
  dan tidak terikat aturan satu antrean aktif per akun.
- **Catatan teknologi**
  - MariaDB (XAMPP) tidak mendukung *partial/functional index* ala PostgreSQL,
    sehingga aturan "satu antrean aktif" diwujudkan dengan *generated column*.
  - Pesan pengingat "0 antrean di depan" sengaja tidak dikirim.

## 7. Di Luar Ruang Lingkup

Proposal tidak menganggarkan, sehingga tidak diimplementasikan: data dokter &
jadwal praktik, pendaftaran kunjungan, data rekam medis, dan laporan/antrean
rekap. Notifikasi WhatsApp/SMS juga di luar lingkup (notifikasi in-app).

## 8. Tim & Pembagian Tugas

| Personel | Kontribusi |
|---|---|
| Farhan | Backend: service & aturan antrean (AntreanService, NotifikasiService) |
| Maulana | Frontend: tampilan Blade + CSS, konsisten untuk 3 peran |
| Daffa | Basis data: migrasi, ERD, constraint KNF-01 |
| Salma | Pengujian: PHPUnit + Dusk (95 + 18 test) |
| Galih | Dokumentasi & konfigurasi lingkungan (README, skrip, seeder) |
| Ghassan | Koordinasi, review, dan integrasi antar bagian |"# PPL" 
