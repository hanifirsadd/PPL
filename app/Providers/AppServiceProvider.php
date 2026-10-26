<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bulan/hari dalam bahasa Indonesia (mis. "14 Oktober 2026") untuk
        // seluruh instance Carbon, termasuk hasil cast tanggal Eloquent.
        Carbon::setLocale(config('app.locale', 'id'));
        Carbon::setUtf8(true);
    }
}
