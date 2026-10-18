<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Report-Only Mode
    |--------------------------------------------------------------------------
    | While true the policy is sent as Content-Security-Policy-Report-Only and
    | only measured. Switch to false (enforce) once the report is clean on
    | staging (docs/foundation/02-security-and-access.md).
    */
    'report_only' => (bool) env('CSP_REPORT_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Report Endpoint
    |--------------------------------------------------------------------------
    | Absolute URL or path that receives violation reports (report-uri). Null
    | keeps the violations in the browser console only.
    */
    'report_uri' => env('CSP_REPORT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Additional Origins
    |--------------------------------------------------------------------------
    | Space-separated origins merged into script-src / connect-src for an
    | approved third party, e.g. a self-hosted analytics script. Nothing else
    | widens the policy; inline scripts and styles use the per-request nonce.
    */
    'script_src' => env('CSP_SCRIPT_SRC', ''),
    'connect_src' => env('CSP_CONNECT_SRC', ''),
];
