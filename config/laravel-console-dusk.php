<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Console Dusk Paths
    |--------------------------------------------------------------------------
    |
    | Here you may configure the name of screenshots and logs directory as you wish.
    */
    'paths' => [
        'screenshots' => storage_path('laravel-console-dusk/screenshots'),
        'log' => storage_path('laravel-console-dusk/log'),
        'source' => storage_path('laravel-console-dusk/source'),
    ],

    /*
    | --------------------------------------------------------------------------
    | Headless Mode
    | --------------------------------------------------------------------------
    |
    | When false it will show a Chrome window while running. Within production
    | it will be forced to run in headless mode.
    */
    'headless' => !env('DUSK_HEADLESS_DISABLED', true),
    // 'headless' => false,

    /*
    | --------------------------------------------------------------------------
    | Driver Configuration
    | --------------------------------------------------------------------------
    |
    | Here you may pass options to the browser driver being automated.
    |
    | A list of available Chromium command line switches is available at
    | https://peter.sh/experiments/chromium-command-line-switches/
    */
    'driver' => [
//        'chrome' => [
//            'options' => [
//                '--disable-gpu',
//            ],
//        ],
        'firefox' => [
            'options' => [
                '--disable-gpu',
                '--no-sandbox',
                '--start-maximized',
                '--disable-setuid-sandbox',
                '--no-first-run',
                '--unhandled-rejections=strict',
                '--disable-blink-features=AutomationControlled',
                '--ignore-certificate-errors',
                '--no-zygote',
                '--single-process'
            ]
        ]
    ],
];
