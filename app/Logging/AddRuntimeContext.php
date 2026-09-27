<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;

/**
 * Monolog tap (config/logging.php): adds service, environment, release and
 * the Nginx request ID to every record, as required by ADR-023.
 *
 * The service name comes from the LOG_SERVICE process variable set by the
 * systemd units (horizon, scheduler, ssr, ...), read at runtime because the
 * configuration is cached once per release by a CLI process.
 *
 * The request ID is read from the REQUEST_ID FastCGI parameter set by Nginx
 * ($request_id, 32 hex chars), never from a client header, so a caller
 * cannot inject arbitrary values into the log.
 */
class AddRuntimeContext
{
    public function __invoke(Logger $logger): void
    {
        $service = getenv('LOG_SERVICE') ?: (PHP_SAPI === 'cli' ? 'cli' : 'web');
        $environment = (string) config('app.env');
        $release = config('ops.release');

        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            return;
        }

        $monolog->pushProcessor(function (LogRecord $record) use ($service, $environment, $release): LogRecord {
            $extra = [
                'service' => $service,
                'environment' => $environment,
                'release' => is_string($release) && $release !== '' ? $release : null,
            ];

            $requestId = $_SERVER['REQUEST_ID'] ?? null;

            if (is_string($requestId) && preg_match('/\A[a-f0-9]{32}\z/', $requestId) === 1) {
                $extra['request_id'] = $requestId;
            }

            return $record->with(extra: [...$record->extra, ...$extra]);
        });
    }
}
