<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public demo
    |--------------------------------------------------------------------------
    |
    | Only for a dedicated demo installation with synthetic data. When on:
    | sample content is seeded outside the local environment, the login page
    | offers a one-click demo administrator, every change (except signing in
    | and out) is refused, and `demo:reset` rebuilds the database every hour.
    | Never enable it on a client site: it signs anyone in as administrator.
    |
    */

    'enabled' => (bool) env('APP_DEMO', false),

    /*
    |--------------------------------------------------------------------------
    | Sample content outside the demo
    |--------------------------------------------------------------------------
    |
    | Switched on only at runtime by SoftwareHouseShowcaseSeeder, so that an
    | explicit seeder run fills a showcase installation with sample content
    | without the sample accounts (known password) of the local environment.
    | Deliberately not read from the environment: a cached configuration
    | must never keep it on.
    |
    */

    'samples' => false,

];
