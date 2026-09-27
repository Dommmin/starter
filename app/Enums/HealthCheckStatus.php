<?php

namespace App\Enums;

/**
 * Result of a single readiness check; Skipped when disabled by configuration.
 */
enum HealthCheckStatus: string
{
    case Ok = 'ok';
    case Fail = 'fail';
    case Skipped = 'skipped';
}
