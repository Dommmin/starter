<?php

namespace App\Actions\Media;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AssistantFeature;
use App\Enums\AuditAction;
use App\Enums\MediaStatus;
use App\Jobs\GenerateImageVariants;
use App\Jobs\GenerateMediaAltText;
use App\Models\MediaAsset;
use App\Services\Media\ScanResult;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Move a quarantined asset to `clean` (and queue image variants) or to
 * `rejected` (the infected bytes are deleted; the record stays for audit).
 * A clean image without alt text also gets an assistant alt text suggestion
 * queued after its variants while that feature is available.
 * Recorded as `media.cleaned` / `media.rejected` by the system actor; a
 * release without a scan (scanner disabled) is audited as `scan: skipped`.
 */
class RecordMediaScanResult
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(MediaAsset $asset, ScanResult $result): void
    {
        $status = DB::transaction(function () use ($asset, $result): ?MediaStatus {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== MediaStatus::Quarantine) {
                return null;
            }

            $status = $result->clean ? MediaStatus::Clean : MediaStatus::Rejected;

            $locked->forceFill([
                'status' => $status,
                'scan_error' => $result->clean ? null : mb_substr('Malware detected: '.$result->signature, 0, 250),
            ])->save();

            $changes = ['status' => RecordAuditEvent::change(MediaStatus::Quarantine->value, $status->value)];

            if (! $result->scanned) {
                $changes['scan'] = RecordAuditEvent::change(null, 'skipped');
            }

            $this->audit->handle(
                $result->clean ? AuditAction::MediaCleaned : AuditAction::MediaRejected,
                $locked,
                null,
                $changes,
            );

            return $status;
        });

        if ($status === MediaStatus::Rejected) {
            Storage::disk($asset->disk)->deleteDirectory(dirname($asset->path));
        }

        if ($status === MediaStatus::Clean && $asset->isImage()) {
            $this->dispatchImageJobs($asset);
        }
    }

    /**
     * Variants first; the alt text suggestion (when available) runs after
     * them, so it can send a downscaled variant instead of the original.
     */
    private function dispatchImageJobs(MediaAsset $asset): void
    {
        if (! AssistantFeature::MediaAltText->isAvailable()) {
            GenerateImageVariants::dispatch($asset->id);

            return;
        }

        Bus::chain([
            new GenerateImageVariants($asset->id),
            new GenerateMediaAltText($asset->id),
        ])->dispatch();
    }
}
