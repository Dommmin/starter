<?php

namespace App\Actions\Media;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update the editable metadata (alternative text) with optimistic locking
 * on `updated_at`, recording `media.updated`.
 */
class UpdateMediaAsset
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @throws ValidationException When the asset changed in the meantime (`conflict`).
     */
    public function handle(MediaAsset $asset, User $actor, ?string $alt, string $expectedUpdatedAt): MediaAsset
    {
        return DB::transaction(function () use ($asset, $actor, $alt, $expectedUpdatedAt): MediaAsset {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.media.conflict'),
                ]);
            }

            if ($locked->alt === $alt) {
                return $locked;
            }

            $this->audit->handle(AuditAction::MediaUpdated, $locked, $actor, [
                'alt' => RecordAuditEvent::change($locked->alt, $alt),
            ]);

            $locked->forceFill(['alt' => $alt, 'updated_at' => $locked->freshTimestamp()])->save();

            return $locked;
        });
    }
}
