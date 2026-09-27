<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browser end-to-end tests (local/testing only)
    |--------------------------------------------------------------------------
    |
    | Synthetic accounts prepared by `php artisan app:e2e-prepare` for the
    | Playwright suite in tests/e2e. `make e2e` generates a random password
    | per run and passes it to the command and the browser container; set
    | E2E_PASSWORD only to reuse a fixed synthetic value. Never real data.
    |
    */

    'password' => env('E2E_PASSWORD'),

    'admin_email' => 'e2e-admin@example.test',

    'editor_email' => 'e2e-editor@example.test',

    /*
     * Content created by the suite uses slugs with this prefix; the command
     * deletes such pages left by previous runs.
     */
    'slug_prefix' => 'e2e-',

];
