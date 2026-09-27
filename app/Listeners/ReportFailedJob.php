<?php

namespace App\Listeners;

use App\Services\Ops\OpsAlerter;
use Illuminate\Queue\Events\JobFailed;

/**
 * Failed job alarm (backend rule: failed jobs have an alarm). Only the job
 * class, queue, connection, job UUID and exception class are reported: the
 * payload and the exception message may contain personal data.
 */
class ReportFailedJob
{
    public function __construct(private readonly OpsAlerter $alerter) {}

    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();

        $this->alerter->critical('queue.job_failed', [
            'job' => $jobName,
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'job_uuid' => $event->job->uuid(),
            'exception' => $event->exception::class,
        ], dedupeKey: $jobName);
    }
}
