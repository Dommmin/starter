<?php

namespace App\Data\Admin\Pages;

use App\Models\Page;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form state for creating or editing a page. `translations` always contains
 * every active public locale; `updatedAt` must be sent back on update for
 * optimistic locking.
 */
#[TypeScript]
class PageFormData extends Data
{
    /**
     * @param  array<string, PageTranslationFormData>  $translations
     */
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public array $translations,
    ) {}

    /**
     * @param  list<string>  $locales
     */
    public static function blank(array $locales): self
    {
        $translations = [];

        foreach ($locales as $locale) {
            $translations[$locale] = PageTranslationFormData::blank();
        }

        return new self(id: null, updatedAt: null, translations: $translations);
    }

    /**
     * Requires the `translations` relation to be eager loaded.
     *
     * @param  list<string>  $locales
     */
    public static function fromPage(Page $page, array $locales): self
    {
        $translations = [];

        foreach ($locales as $locale) {
            $translation = $page->translation($locale);

            $translations[$locale] = $translation === null
                ? PageTranslationFormData::blank()
                : PageTranslationFormData::fromTranslation($translation);
        }

        return new self(
            id: $page->id,
            updatedAt: $page->updated_at?->toIso8601String(),
            translations: $translations,
        );
    }
}
