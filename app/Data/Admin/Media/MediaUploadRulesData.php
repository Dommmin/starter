<?php

namespace App\Data\Admin\Media;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Client-side hints mirroring the upload validation (the server decides).
 */
#[TypeScript]
class MediaUploadRulesData extends Data
{
    /**
     * @param  list<string>  $extensions  Allowed extensions without the dot.
     */
    public function __construct(
        public int $maxBytes,
        public array $extensions,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array<string, list<string>> $allowedTypes */
        $allowedTypes = config('media.allowed_types');

        return new self(
            maxBytes: (int) config('media.max_upload_kb') * 1024,
            extensions: array_merge(...array_values($allowedTypes)),
        );
    }
}
