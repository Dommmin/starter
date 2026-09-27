<?php

$releaseFile = base_path('RELEASE');

return [

    /*
    |--------------------------------------------------------------------------
    | Release identifier
    |--------------------------------------------------------------------------
    |
    | Written by the Deployer recipe into each release directory (RELEASE) and
    | captured by `config:cache` per release, so logs of old and new code are
    | distinguishable during a switch. Empty outside deployed releases.
    |
    */

    'release' => env('APP_RELEASE') ?: (is_file($releaseFile) ? trim((string) file_get_contents($releaseFile)) : null),

    /*
    |--------------------------------------------------------------------------
    | Operator alerts
    |--------------------------------------------------------------------------
    |
    | Critical operational events are always written to the log as `ops.*`
    | entries at level critical. When OPS_ALERT_EMAIL is set, a deduplicated
    | plain-text mail is also sent synchronously (never through the queue that
    | may be the failing component). No SaaS is wired here (open question Q7).
    |
    */

    'alerts' => [
        'mail_to' => env('OPS_ALERT_EMAIL'),
        'dedupe_minutes' => (int) env('OPS_ALERT_DEDUPE_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Readiness probe (/health/ready)
    |--------------------------------------------------------------------------
    |
    | Database, Redis and storage are critical (503 on failure). Queue
    | (Horizon), scheduler heartbeat and the malware scanner only degrade the
    | status (200), so a worker restart during a deploy does not take the web
    | node out. Per-check details are returned only with a valid
    | X-Health-Token header matching HEALTH_TOKEN.
    |
    */

    'health' => [
        'token' => env('HEALTH_TOKEN'),
        'rate_limit_per_minute' => (int) env('HEALTH_RATE_LIMIT', 60),
        'database_connection' => env('HEALTH_DB_CONNECTION'),
        'redis' => (bool) env('HEALTH_CHECK_REDIS', true),
        'redis_connection' => env('HEALTH_REDIS_CONNECTION', 'default'),
        'storage_disk' => env('HEALTH_STORAGE_DISK', 'local'),
        'horizon' => (bool) env('HEALTH_CHECK_HORIZON', true),
        'scheduler' => (bool) env('HEALTH_CHECK_SCHEDULER', true),
        'scanner' => (bool) env('HEALTH_CHECK_SCANNER', false),
        'scanner_timeout' => (int) env('HEALTH_SCANNER_TIMEOUT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler heartbeat
    |--------------------------------------------------------------------------
    |
    | `ops:heartbeat` runs every minute and stores a timestamp in the cache.
    | The readiness probe reports the scheduler as failed when it is older
    | than max_age_seconds.
    |
    */

    'heartbeat' => [
        'cache_key' => 'ops:heartbeat:scheduler',
        'max_age_seconds' => (int) env('OPS_HEARTBEAT_MAX_AGE', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup freshness
    |--------------------------------------------------------------------------
    |
    | scripts/backup/backup.sh writes the UTC completion time of the last
    | successful backup to BACKUP_STATUS_FILE. `ops:check-backup` raises a
    | critical alert when it is missing or older than max_age_hours. Without
    | the variable (local development) the check is skipped.
    |
    */

    'backup' => [
        'status_file' => env('BACKUP_STATUS_FILE'),
        'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 26),
    ],

];
