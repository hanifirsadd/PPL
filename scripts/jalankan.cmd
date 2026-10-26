@echo off
REM ===================================================================
REM  Menjalankan Sistem Manajemen Antrean Puskesmas
REM  Prasyarat: XAMPP terpasang (PHP 8.1+ dan MySQL/MariaDB)
REM ===================================================================
setlocal

set XAMPP=C:\xampp
set PATH=%XAMPP%\php;%PATH%

cd /d "%~dp0.."

echo [1/4] Memeriksa MySQL...
"%XAMPP%\mysql\bin\mysqladmin.exe" -u root ping >nul 2>&1
if errorlevel 1 (
    echo      MySQL belum berjalan. Menjalankan mysqld...
    start "" /b "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file=%XAMPP%\mysql\bin\my.ini
    timeout /t 5 >nul
)

echo [2/4] Menyiapkan database dan data contoh...
php artisan migrate --force
php artisan db:seed --force

echo [3/4] Membersihkan cache...
php artisan config:clear
php artisan view:clear
php artisan cache:clear

echo [4/4] Menjalankan server di http://127.0.0.1:8000
echo.
echo     Akun contoh (kata sandi: password123)
echo       admin@puskesmas.test    - Admin
echo       petugas@puskesmas.test  - Petugas
echo       budi@contoh.test       - Pasien
echo.
php artisan serve --host=127.0.0.1 --port=8000

endlocal