<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Site Name
    |--------------------------------------------------------------------------
    | Used for og:site_name and the WebSite JSON-LD node.
    */
    'site_name' => env('SEO_SITE_NAME', env('APP_NAME', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Default Open Graph Image
    |--------------------------------------------------------------------------
    | Absolute URL or path relative to the application URL. Null disables
    | og:image unless a page provides its own image.
    */
    'default_image' => env('SEO_DEFAULT_IMAGE'),

    /*
    |--------------------------------------------------------------------------
    | Organization (JSON-LD)
    |--------------------------------------------------------------------------
    | Publisher described on the home page. `url` defaults to the application
    | URL; `logo` accepts an absolute URL or a path relative to it.
    */
    'organization' => [
        'name' => env('SEO_ORGANIZATION_NAME', env('APP_NAME', 'Laravel')),
        'url' => env('SEO_ORGANIZATION_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Cache Lifetime
    |--------------------------------------------------------------------------
    | max-age (seconds) of the public sitemap.xml and robots.txt responses.
    */
    'cache_max_age' => (int) env('SEO_CACHE_MAX_AGE', 3600),
];
