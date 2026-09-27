<?php

namespace App\Actions\Media;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\ImageVariantGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently delete an asset: the record (with `media.deleted` in the same
 * transaction), then the private original and all public variants.
 * Rich text referencing the asset stops rendering the image (the renderer
 * drops unknown media ids).
 */
class DeleteMediaAsset
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly ImageVariantGenerator $variants,
    ) {}

    public function handle(MediaAsset $asset, User $actor): void
    {
        DB::transaction(function () use ($asset, $actor): void {
            $this->audit->handle(AuditAction::MediaDeleted, $asset, $actor, [
                'original_name' => RecordAuditEvent::change($asset->original_name, null),
                'status' => RecordAuditEvent::change($asset->status->value, null),
            ]);

            $asset->delete();
        });

        Storage::disk($asset->disk)->deleteDirectory(dirname($asset->path));
        $this->variants->delete($asset);
    }
}
