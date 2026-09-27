<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contact:prune')]
#[Description('Delete contact messages older than the configured retention period')]
class PruneContactMessagesCommand extends Command
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
        $retentionDays = (int) config('contact.retention_days');

        if ($retentionDays < 1) {
            $this->error('The contact retention period (contact.retention_days) must be at least 1 day.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        do {
            $ids = ContactMessage::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $deleted += ContactMessage::query()->whereKey($ids->all())->delete();
            }
        } while ($ids->count() === self::CHUNK_SIZE);

        $this->info(sprintf('Deleted %d contact messages older than %d days.', $deleted, $retentionDays));

        return self::SUCCESS;
    }
}
