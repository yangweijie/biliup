<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Laravel\Dusk\Browser;
use NunoMaduro\LaravelConsoleDusk\Contracts\ManagerContract;
use NunoMaduro\LaravelConsoleDusk\Drivers\Chrome;
use NunoMaduro\LaravelConsoleDusk\Drivers\Firefox;
use NunoMaduro\LaravelConsoleDusk\Manager;
use Symfony\Component\DomCrawler\Crawler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        Browser::macro('crawler', function () {
            return new Crawler(
                $this->driver->getPageSource() ?? '',
                $this->driver->getCurrentURL() ?? ''
            );
        });

        Browser::macro('actualPath', function(){
            return parse_url($this->driver->getCurrentURL(), PHP_URL_PATH) ?? '';
        });

        Http::macro('crew', function () {
            return Http::withHeaders([
                'X-Example' => 'example',
            ])->baseUrl('http://fj.pizhigu.com/dev/crew');
        });

        $this->app->extend(ManagerContract::class, function ($app) {
            $driver = config('laravel-console-dusk.driver');
            return new Manager(array_keys($driver)[0] == 'firefox' ? new Firefox() : new Chrome());
        });
    }
}
