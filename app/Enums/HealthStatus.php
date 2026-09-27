<?php

namespace App\Enums;

/**
 * Overall readiness: Fail when a critical dependency is down (HTTP 503),
 * Degraded when only a background component is unhealthy (HTTP 200).
 */
enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Fail = 'fail';
}
