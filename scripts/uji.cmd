@echo off
REM ===================================================================
REM  Menjalankan seluruh pengujian otomatis (KNF-03 / KT-02)
REM    1. PHPUnit  - aturan bisnis, otentikasi, constraint database
REM    2. Dusk     - pengujian browser (Chrome/Edge) untuk 3 peran
REM ===================================================================
setlocal

set XAMPP=C:\xampp
set PATH=%XAMPP%\php;%XAMPP%\mysql\bin;%PATH%

cd /d "%~dp0.."

echo [1/3] Menyiapkan database pengujian...
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS antrean_puskesmas_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS antrean_puskesmas_dusk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo [2/3] PHPUnit...
php artisan test

echo [3/3] Laravel Dusk (butuh server berjalan di http://127.0.0.1:8000)...
echo       Jika server belum jalan, buka terminal lain lalu jalankan:
echo         php artisan serve --env=dusk.local --host=127.0.0.1 --port=8000
echo.
php artisan dusk

endlocal