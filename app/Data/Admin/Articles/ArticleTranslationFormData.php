<?php

namespace App\Data\Admin\Articles;

use App\Enums\PublicationStatus;
use App\Models\ArticleTranslation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Editable fields of one language version. An empty title means the locale
 * has no translation yet (or that it will be removed on save).
 * `publishedOn` is the publication day (`YYYY-MM-DD`, app timezone).
 */
#[TypeScript]
class ArticleTranslationFormData extends Data
{
    /**
     * @param  array<string, mixed>|null  $body  Tiptap JSON document (`{ type: 'doc', content: [...] }`).
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public ?string $metaDescription,
        #[LiteralTypeScriptType('{ [key: string]: unknown } | null')]
        public ?array $body,
        public PublicationStatus $status,
        public ?string $publishedOn,
    ) {}

    public static function blank(): self
    {
        return new self(
            title: '',
            slug: '',
            excerpt: null,
            metaDescription: null,
            body: null,
            status: PublicationStatus::Draft,
            publishedOn: null,
        );
    }

    public static function fromTranslation(ArticleTranslation $translation): self
    {
        return new self(
            title: $translation->title,
            slug: $translation->slug,
            excerpt: $translation->excerpt,
            metaDescription: $translation->meta_description,
            body: $translation->body,
            status: $translation->status,
            publishedOn: $translation->published_at?->format('Y-m-d'),
        );
    }
}
