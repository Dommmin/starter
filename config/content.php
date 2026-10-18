<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trash retention
    |--------------------------------------------------------------------------
    |
    | Pages and articles moved to the trash are removed for good by the daily
    | `content:purge-trash` command after this number of days. Until then an
    | administrator can restore them; their slugs stay taken.
    |
    */

    'trash_days' => (int) env('CONTENT_TRASH_DAYS', 30),

];
