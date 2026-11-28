<?php

namespace App\Data\Admin\Pages;

use App\Models\ContentBlock;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContentBlockData extends Data
{
    /**
     * @param  int  $uses  Page sections that follow the block, trashed pages included.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public int $uses,
    ) {}

    public static function fromModel(ContentBlock $block): self
    {
        return new self(
            id: $block->id,
            name: $block->name,
            type: $block->type->value,
            uses: (int) $block->getAttribute('sections_count'),
        );
    }
}
