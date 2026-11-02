<?php

namespace App\Data\Admin\Redirects;

use App\Enums\RedirectStatus;
use App\Models\Redirect;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin redirect list.
 */
#[TypeScript]
class RedirectListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $sourcePath,
        public string $target,
        public RedirectStatus $statusCode,
        public bool $isActive,
        public int $hits,
        public ?string $lastHitAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(Redirect $redirect): self
    {
        return new self(
            id: $redirect->id,
            sourcePath: $redirect->source_path,
            target: $redirect->target,
            statusCode: $redirect->status_code,
            isActive: $redirect->is_active,
            hits: $redirect->hits,
            lastHitAt: $redirect->last_hit_at?->toIso8601String(),
            createdAt: $redirect->created_at?->toIso8601String(),
            updatedAt: $redirect->updated_at?->toIso8601String(),
        );
    }
}
