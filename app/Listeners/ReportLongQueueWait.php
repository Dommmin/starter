<?php

namespace App\Listeners;

use App\Services\Ops\OpsAlerter;
use Laravel\Horizon\Events\LongWaitDetected;

/**
 * Horizon fires LongWaitDetected when a queue wait exceeds the `waits`
 * threshold in config/horizon.php (HORIZON_WAIT_THRESHOLD).
 */
class ReportLongQueueWait
{
    public function __construct(private readonly OpsAlerter $alerter) {}

    public function handle(LongWaitDetected $event): void
    {
        $this->alerter->critical('queue.long_wait', [
            'connection' => $event->connection,
            'queue' => $event->queue,
            'wait_seconds' => (int) $event->seconds,
        ], dedupeKey: $event->connection.':'.$event->queue);
    }
}
