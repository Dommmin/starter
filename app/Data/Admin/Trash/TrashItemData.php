<?php

namespace App\Data\Admin\Trash;

use App\Enums\TrashedContentType;
use App\Models\Article;
use App\Models\Page;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One trashed page or article. `title` is the translation of the panel's
 * default content locale, else the first one.
 */
#[TypeScript]
class TrashItemData extends Data
{
    /**
     * @param  list<string>  $locales
     */
    public function __construct(
        public TrashedContentType $type,
        public int $id,
        public ?string $title,
        public array $locales,
        public ?string $deletedAt,
        public ?string $purgeAt,
    ) {}

    /**
     * Requires the `translations` relation loaded without the live-parent scope.
     */
    public static function fromRecord(Page|Article $record, string $locale, int $retentionDays): self
    {
        $translation = $record->translations->firstWhere('locale', $locale) ?? $record->translations->first();

        return new self(
            type: TrashedContentType::of($record),
            id: $record->id,
            title: $translation?->title,
            locales: array_values($record->translations->pluck('locale')->all()),
            deletedAt: $record->deleted_at?->toIso8601String(),
            purgeAt: $record->deleted_at?->addDays($retentionDays)->toIso8601String(),
        );
    }
}
