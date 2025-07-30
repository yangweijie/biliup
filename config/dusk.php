<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Browser
    |--------------------------------------------------------------------------
    |
    | This option controls the default browser that will be used by Laravel
    | Dusk when running browser tests. This should be the name of one of
    | the browsers configured in the "browsers" configuration array.
    |
    */

    'default' => env('DUSK_BROWSER', 'chrome'),

    /*
    |--------------------------------------------------------------------------
    | Browsers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the browsers that will be used by Laravel Dusk.
    | You may configure Chrome, Firefox, or any other browser that supports
    | the WebDriver protocol. All browsers will be started automatically.
    |
    */

    'browsers' => [
        'chrome' => [
            'driver' => 'chrome',
            'options' => [
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-web-security',
                '--disable-features=VizDisplayCompositor',
                '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ],
            'headless' => env('DUSK_HEADLESS', false),
            'maximized' => env('DUSK_START_MAXIMIZED', true),
        ],

        'firefox' => [
            'driver' => 'firefox',
            'options' => [
                '--width=1920',
                '--height=1080',
            ],
            'headless' => env('DUSK_HEADLESS', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Screenshots
    |--------------------------------------------------------------------------
    |
    | Here you may configure if screenshots should be taken when a test
    | fails. You may also configure the directory where the screenshots
    | should be stored.
    |
    */

    'screenshots' => [
        'enabled' => env('DUSK_SCREENSHOTS', true),
        'path' => env('DUSK_SCREENSHOTS_PATH', 'tests/Browser/screenshots'),
        'on_failure' => env('DUSK_SCREENSHOTS_ON_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Logs
    |--------------------------------------------------------------------------
    |
    | Here you may configure if console logs should be captured when a test
    | fails. You may also configure the directory where the logs should be
    | stored.
    |
    */

    'console_logs' => [
        'enabled' => env('DUSK_CONSOLE_LOGS', true),
        'path' => env('DUSK_CONSOLE_LOGS_PATH', 'tests/Browser/console'),
        'on_failure' => env('DUSK_CONSOLE_LOGS_ON_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Code
    |--------------------------------------------------------------------------
    |
    | Here you may configure if source code should be captured when a test
    | fails. You may also configure the directory where the source code
    | should be stored.
    |
    */

    'source' => [
        'enabled' => env('DUSK_SOURCE', true),
        'path' => env('DUSK_SOURCE_PATH', 'tests/Browser/source'),
        'on_failure' => env('DUSK_SOURCE_ON_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | Here you may configure the default timeouts for various operations
    | performed by Laravel Dusk.
    |
    */

    'timeouts' => [
        'implicit_wait' => env('DUSK_IMPLICIT_WAIT', 0),
        'page_load' => env('DUSK_PAGE_LOAD_TIMEOUT', 30),
        'script' => env('DUSK_SCRIPT_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bilibili Specific Settings
    |--------------------------------------------------------------------------
    |
    | Here you may configure Bilibili specific settings for the upload
    | automation process.
    |
    */

    'bilibili' => [
        'login_timeout' => env('BILIBILI_LOGIN_TIMEOUT', 120),
        'upload_timeout' => env('BILIBILI_UPLOAD_TIMEOUT', 600),
        'retry_attempts' => env('BILIBILI_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('BILIBILI_RETRY_DELAY', 5),
        'screenshot_on_error' => env('BILIBILI_SCREENSHOT_ON_ERROR', true),
        'wait_between_uploads' => env('BILIBILI_WAIT_BETWEEN_UPLOADS', 3),
    ],
];
