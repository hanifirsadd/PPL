/* =====================================================================
   Penyegaran otomatis halaman antrean.
   Tabel diambil ulang dari server setiap beberapa detik melalui AJAX,
   sehingga tampilan petugas & pasien selalu sama dengan data terbaru
   tanpa memuat ulang seluruh halaman (tombol aksi tidak terputus).
   ===================================================================== */
(function () {
    'use strict';

    function segarkan(target, url, jeda) {
        var elemen = document.getElementById(target);
        if (!elemen) {
            return;
        }

        var indikator = document.querySelector('[data-segar="[' + target + ']"]');

        function ambil() {
            // Jangan menimpa saat pengguna sedang mengisi/memilih filter.
            if (document.hidden || document.querySelector('input:focus, select:focus, textarea:focus')) {
                return;
            }

            fetch(url, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin',
            })
                .then(function (respons) {
                    return respons.ok ? respons.text() : null;
                })
                .then(function (html) {
                    if (!html || html === elemen.innerHTML) {
                        return;
                    }

                    elemen.innerHTML = html;

                    if (indikator) {
                        indikator.textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID');
                    }
                })
                .catch(function () {
                    /* koneksi terputus sementara: coba lagi pada interval berikutnya */
                });
        }

        window.setInterval(ambil, jeda || 10000);

        // Segarkan langsung ketika tab dikembalikan ke layar, agar data
        // yang baru saja berubah dari tab lain tidak terlihat basi.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                ambil();
            }
        });
    }

    window.antreanSegarkan = segarkan;
})();