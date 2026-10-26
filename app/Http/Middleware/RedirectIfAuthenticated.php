<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // US-02: arahkan pengguna ke dashboard sesuai perannya.
                $pengguna = Auth::guard($guard)->user();

                return redirect()->to(match (true) {
                    $pengguna?->isAdmin() === true => '/admin/poli',
                    $pengguna?->isPetugas() === true => '/petugas/antrean',
                    default => '/pasien/poli',
                });
            }
        }

        return $next($request);
    }
}
