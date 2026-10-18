<?php

namespace App\Console\Commands;

use App\Actions\Content\ManageTrashedContent;
use App\Enums\TrashedContentType;
use App\Repositories\Content\TrashRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:purge-trash')]
#[Description('Remove pages and articles that have been in the trash longer than the retention period')]
class PurgeContentTrashCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TrashRepository $trash, ManageTrashedContent $manage): int
    {
        $days = (int) config('content.trash_days');

        if ($days < 1) {
            $this->error('The trash retention period (content.trash_days) must be at least 1 day.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($days);
        $purged = 0;

        foreach (TrashedContentType::cases() as $type) {
            foreach ($trash->trashedBefore($type, $cutoff) as $record) {
                $manage->purge($record, actor: null);
                $purged++;
            }
        }

        $this->info(sprintf('Removed %d trashed records older than %d days.', $purged, $days));

        return self::SUCCESS;
    }
}
