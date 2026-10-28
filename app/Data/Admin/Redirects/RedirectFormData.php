<?php

namespace App\Data\Admin\Redirects;

use App\Enums\RedirectStatus;
use App\Models\Redirect;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state for creating or editing a redirect. `updatedAt` must be
 * sent back on update for optimistic locking.
 */
#[TypeScript]
class RedirectFormData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public ?string $sourcePath,
        public ?string $target,
        public RedirectStatus $statusCode,
        public bool $isActive,
    ) {}

    public static function blank(): self
    {
        return new self(
            id: null,
            updatedAt: null,
            sourcePath: null,
            target: null,
            statusCode: RedirectStatus::Permanent,
            isActive: true,
        );
    }

    public static function fromModel(Redirect $redirect): self
    {
        return new self(
            id: $redirect->id,
            updatedAt: $redirect->updated_at?->toIso8601String(),
            sourcePath: $redirect->source_path,
            target: $redirect->target,
            statusCode: $redirect->status_code,
            isActive: $redirect->is_active,
        );
    }
}
