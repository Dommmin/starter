<?php

namespace App\Data\Admin\Pages;

use App\Enums\PublicationStatus;
use App\Models\Page;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin page list, described in the list's content locale
 * (falling back to the default public locale when that translation is missing).
 */
#[TypeScript]
class PageListItemData extends Data
{
    /**
     * @param  list<string>  $locales  Locales that have a translation.
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public PublicationStatus $status,
        public string $locale,
        public array $locales,
        public ?string $updatedAt,
    ) {}

    /**
     * Requires the `translations` relation to be eager loaded.
     */
    public static function fromPage(Page $page, string $contentLocale, string $defaultLocale): self
    {
        $translation = $page->translation($contentLocale)
            ?? $page->translation($defaultLocale)
            ?? $page->translations->first();

        $locales = [];
        foreach ($page->translations as $pageTranslation) {
            $locales[] = $pageTranslation->locale;
        }
        sort($locales);

        return new self(
            id: $page->id,
            title: $translation->title ?? '',
            slug: $translation->slug ?? '',
            status: $translation->status ?? PublicationStatus::Draft,
            locale: $translation->locale ?? $contentLocale,
            locales: $locales,
            updatedAt: $page->updated_at?->toIso8601String(),
        );
    }
}
