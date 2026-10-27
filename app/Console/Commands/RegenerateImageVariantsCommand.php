<?php

namespace App\Console\Commands;

use App\Enums\MediaStatus;
use App\Jobs\GenerateImageVariants;
use App\Models\MediaAsset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rebuild the public variants of clean images, e.g. after a failed
 * generation, a changed width or quality setting or a codec installed on
 * the host. Queued by default; --sync generates in this process.
 */
#[Signature('media:regenerate-variants
    {--missing : Only images without variants}
    {--sync : Generate in this process instead of queueing}')]
#[Description('Regenerate the public variants of clean images')]
class RegenerateImageVariantsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = MediaAsset::query()
            ->where('status', MediaStatus::Clean->value)
            ->whereIn('mime', MediaAsset::IMAGE_MIMES)
            ->when($this->option('missing'), fn ($query) => $query->whereNull('variants'));

        $count = 0;

        $query->orderBy('id')->each(function (MediaAsset $asset) use (&$count): void {
            if ($this->option('sync')) {
                GenerateImageVariants::dispatchSync($asset->id);
            } else {
                GenerateImageVariants::dispatch($asset->id);
            }

            $count++;
        });

        $this->info(sprintf('%d image(s) %s.', $count, $this->option('sync') ? 'regenerated' : 'queued for regeneration'));

        return self::SUCCESS;
    }
}
