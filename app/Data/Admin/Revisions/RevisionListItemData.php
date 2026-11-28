<?php

namespace App\Data\Admin\Revisions;

use App\Models\ContentRevision;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RevisionListItemData extends Data
{
    /**
     * @param  list<string>  $titles  One "locale: title" per stored language.
     */
    public function __construct(
        public int $id,
        public string $createdAt,
        public ?string $authorName,
        public array $titles,
    ) {}

    public static function fromRevision(ContentRevision $revision): self
    {
        /** @var array<string, array{title?: string}> $translations */
        $translations = $revision->snapshot['translations'] ?? [];
        $titles = [];

        foreach ($translations as $locale => $translation) {
            $titles[] = strtoupper($locale).': '.($translation['title'] ?? '—');
        }

        return new self(
            id: $revision->id,
            createdAt: $revision->created_at->toIso8601String(),
            authorName: $revision->author?->name,
            titles: $titles,
        );
    }
}
