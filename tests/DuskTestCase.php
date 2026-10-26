<?php

namespace Tests;

use Closure;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use Tests\Concerns\SiapkanDataAntrean;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;
    use SiapkanDataAntrean;

    /**
     * Menyiapkan lingkungan pengujian browser.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Basis data di-reset sebelum tiap skenario agar pengujian browser
     * tidak bergantung pada sisa data dari test sebelumnya.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
    }

    /**
     * Seluruh test Dusk memakai satu sesi Chrome, sehingga diperlukan dua
     * pembersihan antar skenario:
     * - sebelum skenario: kembali ke origin aplikasi lalu menghapus cookie.
     *   Cookie bersifat per-origin, jadi penghapusan hanya berhasil saat
     *   browser berada di aplikasi (bukan about:blank); tanpa ini, login
     *   dari test sebelumnya membuat /masuk langsung dialihkan.
     * - sesudah skenario: pindah ke about:blank untuk membatalkan
     *   auto-refresh halaman status yang masih tertunda.
     */
    public function browse(Closure $callback)
    {
        return parent::browse(function ($browser) use ($callback) {
            $browser->driver->get(config('app.url'));
            $browser->driver->manage()->deleteAllCookies();

            $callback($browser);

            $browser->driver->get('about:blank');
        });
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}