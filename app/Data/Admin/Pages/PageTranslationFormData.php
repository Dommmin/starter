<?php

namespace App\Data\Admin\Pages;

use App\Enums\PublicationStatus;
use App\Models\PageTranslation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Editable fields of one language version. An empty title means the locale
 * has no translation yet (or that it will be removed on save).
 */
#[TypeScript]
class PageTranslationFormData extends Data
{
    /**
     * @param  array<string, mixed>|null  $body  Tiptap JSON document (`{ type: 'doc', content: [...] }`).
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $metaDescription,
        #[LiteralTypeScriptType('{ [key: string]: unknown } | null')]
        public ?array $body,
        public PublicationStatus $status,
        public ?string $publishedAt,
    ) {}

    public static function blank(): self
    {
        return new self(
            title: '',
            slug: '',
            metaDescription: null,
            body: null,
            status: PublicationStatus::Draft,
            publishedAt: null,
        );
    }

    public static function fromTranslation(PageTranslation $translation): self
    {
        return new self(
            title: $translation->title,
            slug: $translation->slug,
            metaDescription: $translation->meta_description,
            body: $translation->body,
            status: $translation->status,
            publishedAt: $translation->published_at?->toIso8601String(),
        );
    }
}
