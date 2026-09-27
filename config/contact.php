<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recipient
    |--------------------------------------------------------------------------
    |
    | Address that receives contact form messages. Falls back to the mail
    | "from" address. The sender's address is set only as Reply-To.
    |
    */

    'recipient' => env('CONTACT_RECIPIENT') ?: env('MAIL_FROM_ADDRESS', 'hello@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Spam protection and rate limits
    |--------------------------------------------------------------------------
    |
    | A submission faster than `min_fill_seconds` after the form was rendered
    | is treated as spam (silently accepted, not stored). A form token older
    | than `form_token_max_age_minutes` asks the visitor to reload the page.
    | Limits count attempts per hour in the cache; no IP is persisted.
    |
    */

    'min_fill_seconds' => (int) env('CONTACT_MIN_FILL_SECONDS', 3),

    'form_token_max_age_minutes' => (int) env('CONTACT_FORM_TOKEN_MAX_AGE_MINUTES', 1440),

    'rate_limits' => [
        'per_ip_per_hour' => (int) env('CONTACT_RATE_LIMIT_IP', 5),
        'per_email_per_hour' => (int) env('CONTACT_RATE_LIMIT_EMAIL', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery recovery and retention
    |--------------------------------------------------------------------------
    |
    | `contact:retry-failed` re-dispatches failed messages and pending ones
    | untouched for `stale_pending_minutes` (lost jobs), up to
    | `max_total_attempts` attempts in total. `contact:prune` deletes messages
    | older than `retention_days` (a business/legal decision of the owner).
    |
    */

    'stale_pending_minutes' => (int) env('CONTACT_STALE_PENDING_MINUTES', 30),

    'max_total_attempts' => (int) env('CONTACT_MAX_TOTAL_ATTEMPTS', 12),

    'retention_days' => (int) env('CONTACT_RETENTION_DAYS', 180),

];
