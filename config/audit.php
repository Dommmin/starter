<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit log retention
    |--------------------------------------------------------------------------
    |
    | Audit entries older than this number of days are removed by the daily
    | `audit:prune` command. Document 02 proposes 180 days for the admin audit
    | log; the final value is a business/legal decision of the owner.
    |
    */

    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

];
