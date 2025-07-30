<?php

namespace NunoMaduro\LaravelConsoleDusk\Drivers;

use Closure;
use Derekmd\Dusk\Concerns\TogglesHeadlessMode;
use Derekmd\Dusk\Firefox\SupportsFirefox;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use NunoMaduro\LaravelConsoleDusk\Contracts\Drivers\DriverContract;

class Firefox implements DriverContract
{

    use SupportsFirefox;
    use TogglesHeadlessMode;

    public function open(): void
    {
        self::startFirefoxDriver($this->hasHeadlessDisabled()?[]:['--headless']);
    }

    public function close(): void
    {
        static::stopFirefoxDriver();
    }

    public function getDriver()
    {
        $capabilities = DesiredCapabilities::firefox();

        $capabilities->getCapability(FirefoxOptions::CAPABILITY)
            ->addArguments($this->filterHeadlessArguments([
                '--headless',
            ]))
            ->setPreference('devtools.console.stdout.content', true);

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? 'http://localhost:4444',
            $capabilities
        );
    }

    public static function afterClass(Closure $callback): void
    {
        // ..
    }

    /**
     * Running around headless, or not..
     */
    protected function runHeadless(): ?string
    {
        return ! config('laravel-console-dusk.headless', true) && ! app()->isProduction() ? null : '--headless';
    }

    /**
     * Determine whether the Dusk command has disabled headless mode.
     */
    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
            isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }

    /**
     * Determine if the browser window should start maximized.
     */
    protected function shouldStartMaximized(): bool
    {
        return isset($_SERVER['DUSK_START_MAXIMIZED']) ||
            isset($_ENV['DUSK_START_MAXIMIZED']);
    }

    /**
     * Determine if the tests are running within Laravel Sail.
     *
     * @return bool
     */
    protected static function runningInSail()
    {
        return isset($_ENV['LARAVEL_SAIL']) && $_ENV['LARAVEL_SAIL'] == '1';
    }

    public function __destruct()
    {
        $this->close();
    }
}
