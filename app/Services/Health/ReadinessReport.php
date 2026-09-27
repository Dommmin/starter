<?php

namespace App\Services\Health;

use App\Enums\HealthCheckStatus;
use App\Enums\HealthStatus;

/**
 * Immutable outcome of one readiness probe run.
 */
final readonly class ReadinessReport
{
    /**
     * @param  array<string, HealthCheckStatus>  $checks
     */
    public function __construct(
        public HealthStatus $status,
        public array $checks,
    ) {}

    /**
     * @return array<string, string>
     */
    public function checksToArray(): array
    {
        return array_map(fn (HealthCheckStatus $status): string => $status->value, $this->checks);
    }
}
