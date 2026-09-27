<?php

namespace App\Jobs;

use App\Actions\Media\RecordMediaScanResult;
use App\Enums\MediaStatus;
use App\Models\MediaAsset;
use App\Services\Media\Exceptions\MalwareScannerUnavailable;
use App\Services\Media\MalwareScanner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Scan a quarantined upload with the malware scanner. Idempotent: assets
 * that already left quarantine are skipped. While the scanner is
 * unavailable the asset stays in quarantine with `scan_error` and the job is
 * retried with backoff; after the last attempt it stays in quarantine and
 * can be re-queued (`failed_jobs`).
 */
class ScanMediaAsset implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Below the Redis `retry_after` (90 s) of the default queue connection.
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
        return [30, 120, 300, 900];
    }

    public function handle(MalwareScanner $scanner, RecordMediaScanResult $recordResult): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if ($asset === null || $asset->status !== MediaStatus::Quarantine) {
            return;
        }

        $stream = Storage::disk($asset->disk)->readStream($asset->path);

        if ($stream === null) {
            $this->markScanError($asset, 'The quarantined file is missing.');

            return;
        }

        try {
            $result = $scanner->scan($stream);
        } catch (MalwareScannerUnavailable $exception) {
            $this->markScanError($asset, $exception->getMessage());

            throw $exception;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $recordResult->handle($asset, $result);
    }

    /**
     * The asset stays in quarantine after the last failed attempt.
     */
    public function failed(?Throwable $exception): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);

        if ($asset !== null && $asset->status === MediaStatus::Quarantine && $asset->scan_error === null) {
            $this->markScanError($asset, 'Scan failed after all attempts.');
        }
    }

    private function markScanError(MediaAsset $asset, string $message): void
    {
        $asset->forceFill(['scan_error' => mb_substr($message, 0, 250)])->save();
    }
}
