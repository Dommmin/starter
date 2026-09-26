<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Locales Registry
    |--------------------------------------------------------------------------
    | All locales recognized by the application, with native labels and text direction.
    */
    'registry' => [
        'en' => [
            'code' => 'en',
            'name' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
        ],
        'pl' => [
            'code' => 'pl',
            'name' => 'Polish',
            'native' => 'Polski',
            'dir' => 'ltr',
        ],
        'de' => [
            'code' => 'de',
            'name' => 'German',
            'native' => 'Deutsch',
            'dir' => 'ltr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Locales Configuration
    |--------------------------------------------------------------------------
    */
    'public_locales' => array_values(array_filter(explode(',', (string) env('APP_PUBLIC_LOCALES', 'en,pl,de')))),
    'public_default' => env('APP_PUBLIC_DEFAULT', 'en'),
    'public_fallback' => env('APP_PUBLIC_FALLBACK', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Admin Locales Configuration
    |--------------------------------------------------------------------------
    | Admin panel always requires English ('en') as an active locale and as fallback.
    */
    'admin_locales' => array_values(array_filter(explode(',', (string) env('APP_ADMIN_LOCALES', 'en,pl,de')))),
    'admin_default' => 'en',
    'admin_fallback' => 'en',
];
