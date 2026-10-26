<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otorisasi berbasis peran (KNF-02): memeriksa autentikasi DAN peran
 * (Pasien / Petugas / Admin) sebelum request mencapai controller —
 * sesuai arsitektur proposal (middleware → controller → service).
 *
 * Pemakaian: ->middleware('peran:PASIEN') atau ->middleware('peran:PETUGAS,ADMIN')
 */
class EnsurePeran
{
    public function handle(Request $request, Closure $next, string ...$peranDiizinkan): Response
    {
        $pengguna = $request->user();

        if ($pengguna === null) {
            return redirect()->route('login');
        }

        if (! in_array($pengguna->peran, $peranDiizinkan, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman tersebut.');
        }

        return $next($request);
    }
}
