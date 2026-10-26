# PANDUAN SISTEM MANAJEMEN ANTREAN PUSKEMAS

Panduan penggunaan & operasional aplikasi. Untuk dokumentasi teknis (struktur
kode, basis data, pengujian), baca `README.md`.

---

## A. Menjalankan Aplikasi

1. Buka **XAMPP Control Panel**, tekan **Start** pada modul **MySQL**.
   (Bila memakai skrip `scripts\jalankan.cmd`, MySQL dinyalakan otomatis.)
2. Jalankan `scripts\jalankan.cmd`, atau manual:

   ```bat
   set PATH=C:\xampp\php;%PATH%
   php artisan migrate --seed
   php artisan serve --host=127.0.0.1 --port=8000
   ```

3. Buka <http://127.0.0.1:8000> pada peramban.

Aplikasi hanya menampilkan tombol **Ambil Antrean** pada poli yang sedang
terbuka (mengikuti jam operasional) dan kuotanya belum habis.

## B. Peran dan Halaman

### Tamu (tanpa akun)

| Halaman | Fungsi |
|---|---|
| Papan Antrean (`/papan`) | Melihat nomor yang sedang dilayani, yang sedang dipanggil, dan antrean berikutnya per poli. Tersedia di halaman masuk melalui tombol **Masuk sebagai Tamu**, atau lewat menu **Papan Antrean** bagi pengunjung yang belum masuk |

Tamu **hanya melihat** — tidak dapat mengambil nomor antrean maupun melihat
identitas pasien. Untuk mengambil antrean, pasien wajib masuk atau mendaftar.

### Pasien

| Halaman | Fungsi |
|---|---|
| Ambil Antrean | Memilih poli aktif dan mengambil nomor antrean online (US-03, US-04) |
| Status Antrean | Nomor antrean, jumlah antrean di depan, nomor yang sedang dilayani (US-06, US-07); halaman muat ulang otomatis tiap 10 detik |
| Riwayat | Riwayat seluruh antrean yang pernah diambil |
| Notifikasi | Pengingat "tersisa 3 antrean" (US-08) dan pemberitahuan nomor dipanggil (US-09) |

### Petugas

| Halaman | Fungsi |
|---|---|
| Daftar Antrean | Antrean hari ini dengan filter per poli dan status (US-10), serta tombol panggil/mulai/selesai/lewati/panggil ulang (US-11 s/d US-15) |
| Input Manual | Mencatat pasien yang datang langsung tanpa mengambil nomor secara online (US-05) |

### Admin

| Halaman | Fungsi |
|---|---|
| Data Poli | Tambah, ubah, dan hapus/nonaktifkan poli (US-16) |
| Jam Operasional | Menentukan hari serta jam buka dan tutup tiap poli (US-17) |
| Kuota | Batas jumlah antrean per poli per tanggal (US-18) |

Admin juga dapat membuka Daftar Antrean untuk keperluan pengawasan.

## C. Alur Kerja Sehari-hari

1. **Admin** menyiapkan poli, jam operasional, dan kuota harian.
2. **Pasien** membuka situs, mendaftar atau masuk, memilih poli, lalu menekan
   **Ambil Antrean** dan memperoleh nomor berawalan kode poli (contoh `PGT-0007`).
3. **Petugas memanggil antrean** dengan salah satu dari dua cara:
   - **📞 Panggil Berikutnya (US-11)** — memanggil nomor teratas (paling kecil)
     pada poli yang dipilih. Bila filter poli belum diisi, pilih poli langsung
     pada kotak "Poli yang dipanggil".
   - **📞 Panggil <nomor> (US-11)** pada baris tertentu — memanggil nomor itu
     secara spesifik, berguna untuk pasien walk-in yang-rombel atau pasien
     yang kembali setelah sempat dilewati.
   - Setelah dipanggil: status **MENUNGGU → DIPANGGIL**, pasien langsung
     menerima notifikasi, lalu petugas menekan:
     - **Mulai Layani** (DIPANGGIL → DILAYANI) bila pasien hadir;
     - **Selesai** (DILAYANI → SELESAI) setelah pelayanan rampung;
     - **Lewati** (DIPANGGIL → DILEWATI) bila tidak hadir, lalu dapat
       **Panggil Ulang** (DILEWATI → DIPANGGIL).
4. Pasien yang tersisa tiga antrean di depannya menerima pengingat otomatis.

Daftar antrean (petugas) dan status antrean (pasien) **menyegarkan diri otomatis
setiap 10 detik**, sehingga kedua tampilan selalu sama tanpa perlu memuat ulang
halaman.

## D. Mengurutkan Daftar

Seluruh daftar dapat diurutkan. Pada tabel, klik judul kolomnya (▲/▼ menandai
kolom aktif; klik sekali lagi untuk membalik arah). Pada daftar berbentuk kartu,
gunakan tombol **Urutkan** di bagian atas.

| Halaman | Pilihan urut | Urutan bawaan |
|---|---|---|
| Pasien — Pilih Poli | Nama poli, Kode poli, Sisa kuota | Nama poli |
| Pasien — Riwayat | Tanggal, Nomor, Poli, Status | Tanggal (terbaru dulu) |
| Pasien — Notifikasi | Terbaru, Jenis, Nomor antrean | Terbaru |
| Petugas — Daftar Antrean | Nomor, Pasien, Status, Waktu dipanggil | Nomor antrean |
| Admin — Data Poli | Kode, Nama, Status, Jumlah antrean | Nama poli |
| Admin — Jam Operasional | Hari, Jam buka, Jam tutup | Hari (Senin → Minggu) |
| Admin — Kuota | Tanggal, Poli, Kuota | Tanggal |

Pengurutan tidak menghapus filter yang sedang aktif: memfilter poli lalu mengurutkan
tetap menampilkan hanya poli tersebut. Pilihan yang tidak dikenal diabaikan
dan sistem kembali ke urutan bawaan.

## E. Aturan yang Ditegakkan

| Aturan | Dampak |
|---|---|
| Maksimal satu antrean aktif per pasien per poli per hari (KNF-01) | Pengambilan kedua di poli yang sama ditolak, baik oleh aplikasi maupun constraint database |
| Kuota harian per poli | Pengambilan online dan input manual ditolak saat kuota habis |
| Jam operasional | Pengambilan hanya dapat dilakukan saat poli buka |
| Nomor unik per poli per hari | Antrean online dan manual berbagi satu urutan nomor; nomor berawalan kode poli (PU-0001, PGT-0002) sehingga tidak pernah sama antar poli |
| Satu pasien dilayani per poli | Tombol **Mulai Layani** kedua pada poli yang sama ditolak |
| Transisi status | Lompatan status (misal MENUNGGU langsung DILAYANI) ditolak |
| Anti-duplikasi notifikasi | Pengingat dikirim satu kali per antrean |

## F. Menambah Pengguna

- **Pasien** mendaftar sendiri melalui halaman `/daftar`.
- **Petugas** dan **Admin** dibuat administrator, misalnya:

  ```bat
  php artisan tinker
  >>> App\Models\User::create(['nama' => 'Petugas 2', 'email' => 'petugas2@puskesmas.test', 'password' => 'rahasia123', 'peran' => 'PETUGAS']);
  ```

## G. Menjalankan Pengujian Otomatis

```bat
scripts\uji.cmd
```

atau terpisah:

```bat
php artisan test     :: 95 test aturan bisnis dan antarmuka
php artisan dusk     :: 18 test browser sungguhan
```

Untuk Dusk, server harus berjalan dengan environment khusus:

```bat
php artisan serve --env=dusk.local --host=127.0.0.1 --port=8000
```

Apabila suatu test gagal, Dusk menyimpan tangkapan layar pada
`tests/Browser/screenshots/` untuk membantu menelusuri masalah.

## H. Pemecahan Masalah

| Gejala | Penyebab & Solusi |
|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL belum berjalan; nyalakan lewat XAMPP Control Panel. |
| `Base table or view not found` | Basis data belum siap; jalankan `php artisan migrate`. |
| Halaman menampilkan "Whoops, an error occurred" | Periksa `storage/logs/laravel.log` untuk detail. |
| Perintah `php` tidak dikenali | Tambahkan `C:\xampp\php` ke PATH atau gunakan skrip `.cmd`. |
| Semua poli tampil "Tutup" | Sesuai desain: di luar jam operasional pasien tidak dapat mengambil antrean. |
| Pasien walk-in tidak menerima notifikasi | Walk-in tidak memiliki akun, sehingga tidak ada kotak notifikasi; petugas tetap dapat memanggilnya lewat tombol **Panggil &lt;nomor&gt;** pada barisnya. |