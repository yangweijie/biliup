<?php

namespace Tests;

use Laravel\Dusk\TestCase as BaseTestCase;
use NunoMaduro\LaravelConsoleDusk\Drivers\Chrome;
use NunoMaduro\LaravelConsoleDusk\Drivers\Firefox;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Prepare for Dusk test execution.
     *
     * @beforeClass
     */
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver();
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): \Facebook\WebDriver\Remote\RemoteWebDriver
    {
        $options = (new \Facebook\WebDriver\Chrome\ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
        ])->unless($this->hasHeadlessDisabled(), function ($items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return \Facebook\WebDriver\Remote\RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? 'http://localhost:9515',
            \Facebook\WebDriver\Remote\DesiredCapabilities::chrome()->setCapability(
                \Facebook\WebDriver\Chrome\ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * Determine whether the Dusk command has disabled headless mode.
     */
    protected function hasHeadlessDisabled(): bool
    {
        // 先尝试从环境变量获取
        $envDisabled = isset($_ENV['DUSK_HEADLESS_DISABLED']) || isset($_SERVER['DUSK_HEADLESS_DISABLED']);

        // 如果环境变量没有设置，尝试从 .env 文件读取
        if (!$envDisabled && file_exists('.env')) {
            $envContent = file_get_contents('.env');
            if (strpos($envContent, 'DUSK_HEADLESS_DISABLED=true') !== false) {
                return true;
            }
        }

        return $envDisabled;
    }

    /**
     * Determine whether the Dusk command has enabled start maximized mode.
     */
    protected function shouldStartMaximized(): bool
    {
        return isset($_ENV['DUSK_START_MAXIMIZED']) ||
               isset($_SERVER['DUSK_START_MAXIMIZED']);
    }
}
