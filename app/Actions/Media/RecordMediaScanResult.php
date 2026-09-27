<?php

namespace App\Actions\Media;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\MediaStatus;
use App\Jobs\GenerateImageVariants;
use App\Models\MediaAsset;
use App\Services\Media\ScanResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Move a quarantined asset to `clean` (and queue image variants) or to
 * `rejected` (the infected bytes are deleted; the record stays for audit).
 * Recorded as `media.cleaned` / `media.rejected` by the system actor.
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

            $this->audit->handle(
                $result->clean ? AuditAction::MediaCleaned : AuditAction::MediaRejected,
                $locked,
                null,
                ['status' => RecordAuditEvent::change(MediaStatus::Quarantine->value, $status->value)],
            );

            return $status;
        });

        if ($status === MediaStatus::Rejected) {
            Storage::disk($asset->disk)->deleteDirectory(dirname($asset->path));
        }

        if ($status === MediaStatus::Clean && $asset->isImage()) {
            GenerateImageVariants::dispatch($asset->id);
        }
    }
}
