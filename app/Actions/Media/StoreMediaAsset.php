<?php

namespace App\Actions\Media;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\MediaStatus;
use App\Jobs\ScanMediaAsset;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Store an already validated upload in quarantine on the private media disk
 * under a random name, record `media.uploaded` and queue the malware scan
 * after the transaction commits.
 */
class StoreMediaAsset
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  string  $mime  MIME type detected from the file content.
     * @param  string  $originalName  Sanitized client file name (display only).
     */
    public function handle(User $owner, UploadedFile $file, string $mime, string $originalName): MediaAsset
    {
        /** @var array<string, list<string>> $allowedTypes */
        $allowedTypes = config('media.allowed_types');
        $disk = (string) config('media.disk');
        $uuid = (string) Str::uuid();
        $path = "{$uuid}/original.{$allowedTypes[$mime][0]}";

        $dimensions = in_array($mime, MediaAsset::IMAGE_MIMES, true)
            ? @getimagesize((string) $file->getRealPath())
            : false;

        Storage::disk($disk)->putFileAs($uuid, $file, basename($path));

        try {
            return DB::transaction(function () use ($owner, $file, $mime, $originalName, $disk, $uuid, $path, $dimensions): MediaAsset {
                $asset = new MediaAsset;
                $asset->forceFill([
                    'uuid' => $uuid,
                    'owner_id' => $owner->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime' => $mime,
                    'size' => (int) $file->getSize(),
                    'width' => $dimensions === false ? null : $dimensions[0],
                    'height' => $dimensions === false ? null : $dimensions[1],
                    'checksum' => (string) hash_file('sha256', (string) $file->getRealPath()),
                    'status' => MediaStatus::Quarantine,
                ])->save();

                $this->audit->handle(AuditAction::MediaUploaded, $asset, $owner, [
                    'original_name' => RecordAuditEvent::change(null, $originalName),
                    'mime' => RecordAuditEvent::change(null, $mime),
                    'status' => RecordAuditEvent::change(null, MediaStatus::Quarantine->value),
                ]);

                ScanMediaAsset::dispatch($asset->id)->afterCommit();

                return $asset;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->deleteDirectory($uuid);

            throw $exception;
        }
    }
}
