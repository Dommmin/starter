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

];
