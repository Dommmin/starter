<?php

namespace App\Jobs;

use App\Actions\Assistant\Exceptions\AssistantBudgetExceeded;
use App\Actions\Assistant\SuggestMediaAltText;
use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AssistantFeature;
use App\Enums\AuditAction;
use App\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fill in the missing alt text of a clean image with an assistant
 * suggestion, recorded as `media.updated` by the system actor. Idempotent:
 * skipped while the feature is unavailable, for assets that are not clean
 * images and for assets that already have an alt text (a human text always
 * wins, also when it was saved while the provider was answering). A
 * provider failure is retried with backoff; a used up monthly budget is not.
 */
class GenerateMediaAltText implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Above `assistant.timeout`, below the Redis `retry_after` (90 s) of the
     * default queue connection.
     */
    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $mediaAssetId) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaAssetId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SuggestMediaAltText $suggest, RecordAuditEvent $audit): void
    {
        if (! AssistantFeature::MediaAltText->isAvailable()) {
            return;
        }

        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if ($asset === null || filled($asset->alt)) {
            return;
        }

        try {
            $alt = $suggest->handle($asset, null);
        } catch (AssistantBudgetExceeded) {
            Log::info('Assistant alt text skipped: monthly budget used up.', ['media_asset_id' => $asset->id]);

            return;
        }

        if ($alt === null) {
            return;
        }

        DB::transaction(function () use ($asset, $alt, $audit): void {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->hasPassedScan() || filled($locked->alt)) {
                return;
            }

            $audit->handle(AuditAction::MediaUpdated, $locked, null, [
                'alt' => RecordAuditEvent::change($locked->alt, $alt),
            ]);

            $locked->forceFill(['alt' => $alt, 'updated_at' => $locked->freshTimestamp()])->save();
        });
    }
}
