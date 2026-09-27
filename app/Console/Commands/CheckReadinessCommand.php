<?php

namespace App\Console\Commands;

use App\Enums\HealthStatus;
use App\Services\Health\ReadinessProbe;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ops:readiness {--strict : Fail also when a background component is degraded}')]
#[Description('Run the readiness probe from the CLI (deploy smoke before the release switch)')]
class CheckReadinessCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReadinessProbe $probe): int
    {
        $report = $probe->run();

        foreach ($report->checksToArray() as $check => $status) {
            $this->line(sprintf('%-10s %s', $check, $status));
        }

        $this->line('status     '.$report->status->value);

        $failed = $report->status === HealthStatus::Fail
            || ($this->option('strict') && $report->status === HealthStatus::Degraded);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
