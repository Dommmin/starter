<?php

namespace App\Data\Admin\Redirects;

use App\Models\NotFoundLog;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Aggregated 404 log entry shown in the redirects panel.
 */
#[TypeScript]
class NotFoundLogItemData extends Data
{
    public function __construct(
        public int $id,
        public string $path,
        public int $hits,
        public ?string $lastReferer,
        public ?string $lastSeenAt,
    ) {}

    public static function fromModel(NotFoundLog $log): self
    {
        return new self(
            id: $log->id,
            path: $log->path,
            hits: $log->hits,
            lastReferer: $log->last_referer,
            lastSeenAt: $log->last_seen_at->toIso8601String(),
        );
    }
}
