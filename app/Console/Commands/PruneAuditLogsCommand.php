<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:prune')]
#[Description('Delete audit log entries older than the configured retention period')]
class PruneAuditLogsCommand extends Command
{
    /**
     * Rows deleted per query, to keep locks short on large tables.
     */
    private const int CHUNK_SIZE = 1000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = (int) config('audit.retention_days');

        if ($retentionDays < 1) {
            $this->error('The audit retention period (audit.retention_days) must be at least 1 day.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        do {
            $ids = AuditLog::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $deleted += AuditLog::query()->whereKey($ids->all())->delete();
            }
        } while ($ids->count() === self::CHUNK_SIZE);

        $this->info(sprintf('Deleted %d audit log entries older than %d days.', $deleted, $retentionDays));

        return self::SUCCESS;
    }
}
