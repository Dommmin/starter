<?php

namespace App\Services\Health;

use App\Enums\HealthCheckStatus;
use App\Enums\HealthStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Readiness of the application node, shared by GET /health/ready and
 * `php artisan ops:readiness` (pre-switch deploy smoke).
 *
 * Critical checks (database, redis, storage) turn the report into Fail;
 * background checks (queue/Horizon, scheduler heartbeat, scanner) only into
 * Degraded. Every check catches its own failure, so one dependency cannot
 * hide the others. Connection timeouts come from the driver configuration
 * (DB_CONNECT_TIMEOUT, REDIS_TIMEOUT, HEALTH_SCANNER_TIMEOUT).
 */
class ReadinessProbe
{
    /**
     * @var list<string>
     */
    public const array CRITICAL_CHECKS = ['database', 'redis', 'storage'];

    public function __construct(private readonly MasterSupervisorRepository $masterSupervisors) {}

    public function run(): ReadinessReport
    {
        $checks = [
            'database' => $this->guard(fn (): bool => $this->database()),
            'redis' => config('ops.health.redis') ? $this->guard(fn (): bool => $this->redis()) : HealthCheckStatus::Skipped,
            'storage' => $this->guard(fn (): bool => $this->storage()),
            'queue' => $this->shouldCheckHorizon() ? $this->guard(fn (): bool => $this->horizon()) : HealthCheckStatus::Skipped,
            'scheduler' => config('ops.health.scheduler') ? $this->guard(fn (): bool => $this->scheduler()) : HealthCheckStatus::Skipped,
            'scanner' => config('ops.health.scanner') ? $this->guard(fn (): bool => $this->scanner()) : HealthCheckStatus::Skipped,
        ];

        return new ReadinessReport($this->overallStatus($checks), $checks);
    }

    /**
     * @param  array<string, HealthCheckStatus>  $checks
     */
    private function overallStatus(array $checks): HealthStatus
    {
        $failed = array_keys(array_filter($checks, fn (HealthCheckStatus $status): bool => $status === HealthCheckStatus::Fail));

        if (array_intersect($failed, self::CRITICAL_CHECKS) !== []) {
            return HealthStatus::Fail;
        }

        return $failed === [] ? HealthStatus::Ok : HealthStatus::Degraded;
    }

    /**
     * @param  callable(): bool  $check
     */
    private function guard(callable $check): HealthCheckStatus
    {
        try {
            return $check() ? HealthCheckStatus::Ok : HealthCheckStatus::Fail;
        } catch (Throwable) {
            return HealthCheckStatus::Fail;
        }
    }

    private function database(): bool
    {
        $connection = config('ops.health.database_connection');

        return DB::connection(is_string($connection) && $connection !== '' ? $connection : null)
            ->select('select 1 as ok') !== [];
    }

    private function redis(): bool
    {
        $reply = Redis::connection((string) config('ops.health.redis_connection'))->ping();

        return $reply === true || $reply === 'PONG' || $reply === '+PONG';
    }

    private function storage(): bool
    {
        $disk = Storage::disk((string) config('ops.health.storage_disk'));
        $path = 'health/'.Str::uuid()->toString();

        try {
            return $disk->put($path, 'ok') && $disk->get($path) === 'ok';
        } finally {
            $disk->delete($path);
        }
    }

    private function shouldCheckHorizon(): bool
    {
        $connection = (string) config('queue.default');

        return (bool) config('ops.health.horizon')
            && config("queue.connections.{$connection}.driver") === 'redis';
    }

    /**
     * Same rule as `php artisan horizon:status`: at least one master
     * supervisor and none of them paused.
     */
    private function horizon(): bool
    {
        $masters = $this->masterSupervisors->all();

        if ($masters === []) {
            return false;
        }

        foreach ($masters as $master) {
            if (($master->status ?? null) === 'paused') {
                return false;
            }
        }

        return true;
    }

    private function scheduler(): bool
    {
        // The Redis cache store keeps integers unserialized and returns them as numeric strings.
        $beat = Cache::get((string) config('ops.heartbeat.cache_key'));

        return is_numeric($beat)
            && now()->getTimestamp() - (int) $beat <= (int) config('ops.heartbeat.max_age_seconds');
    }

    /**
     * clamd PING/PONG with a short timeout (the scan itself uses its own).
     */
    private function scanner(): bool
    {
        $timeout = max(1, (int) config('ops.health.scanner_timeout'));
        $socket = @fsockopen((string) config('media.scanner.host'), (int) config('media.scanner.port'), $errorCode, $errorMessage, $timeout);

        if ($socket === false) {
            return false;
        }

        try {
            stream_set_timeout($socket, $timeout);
            fwrite($socket, "zPING\0");

            return trim((string) fread($socket, 16), "\0\n") === 'PONG';
        } finally {
            fclose($socket);
        }
    }
}
