<?php

namespace App\Console\Commands;

use App\Services\Ops\OpsAlerter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ops:check-backup')]
#[Description('Alert when the last successful backup is missing or too old')]
class CheckBackupFreshnessCommand extends Command
{
    /**
     * Execute the console command.
     *
     * The status file is written by scripts/backup/backup.sh after a
     * complete, verified backup. This is a second line of defence: the
     * backup unit itself alerts on a non-zero exit (OnFailure=).
     */
    public function handle(OpsAlerter $alerter): int
    {
        $statusFile = config('ops.backup.status_file');

        if (! is_string($statusFile) || $statusFile === '') {
            $this->components->info('Backup freshness check skipped: BACKUP_STATUS_FILE is not configured.');

            return self::SUCCESS;
        }

        $maxAgeHours = max(1, (int) config('ops.backup.max_age_hours'));
        $completedAt = $this->readCompletedAt($statusFile);

        if ($completedAt === null) {
            $alerter->critical('backup.missing', ['max_age_hours' => $maxAgeHours], dedupeKey: 'backup');
            $this->components->error('No successful backup recorded.');

            return self::FAILURE;
        }

        $ageHours = (int) floor($completedAt->diffInMinutes(now(), absolute: true) / 60);

        if ($completedAt->lessThan(now()->subHours($maxAgeHours))) {
            $alerter->critical('backup.stale', [
                'age_hours' => $ageHours,
                'max_age_hours' => $maxAgeHours,
            ], dedupeKey: 'backup');
            $this->components->error(sprintf('Last successful backup is %d h old (limit %d h).', $ageHours, $maxAgeHours));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Last successful backup is %d h old.', $ageHours));

        return self::SUCCESS;
    }

    private function readCompletedAt(string $statusFile): ?CarbonImmutable
    {
        if (! is_file($statusFile) || ! is_readable($statusFile)) {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim((string) file_get_contents($statusFile)));
        } catch (Throwable) {
            return null;
        }
    }
}
