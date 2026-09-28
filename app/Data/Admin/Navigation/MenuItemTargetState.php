<?php

namespace App\Data\Admin\Navigation;

use App\Enums\MenuItemType;
use App\Enums\PublicationStatus;
use App\Models\MenuItem;
use Carbon\CarbonImmutable;

/**
 * Editor-side state of a menu item's page/article target in the menu
 * locale. Requires the `pageTranslations` / `articleTranslations` relations
 * loaded for that locale (MenuItemRepository).
 */
final readonly class MenuItemTargetState
{
    public function __construct(
        public ?string $title,
        public bool $missing,
        public bool $hidden,
    ) {}

    public static function of(MenuItem $item): self
    {
        if ($item->isTargetMissing()) {
            return new self(title: null, missing: true, hidden: false);
        }

        if ($item->type === MenuItemType::Article) {
            $translation = $item->articleTranslations->first();

            return new self(
                title: $translation?->title,
                missing: false,
                hidden: $translation === null
                    || $translation->status !== PublicationStatus::Published
                    || $translation->published_at === null
                    || $translation->published_at->greaterThan(CarbonImmutable::now()),
            );
        }

        if ($item->type === MenuItemType::Page || ($item->type === MenuItemType::Anchor && $item->page_id !== null)) {
            $translation = $item->pageTranslations->first();

            return new self(
                title: $translation?->title,
                missing: false,
                hidden: $translation === null || $translation->status !== PublicationStatus::Published,
            );
        }

        return new self(title: null, missing: false, hidden: false);
    }

    /**
     * The explicit label, or the target title in the menu locale.
     */
    public function displayLabel(MenuItem $item): ?string
    {
        $label = trim((string) $item->label);

        return $label !== '' ? $label : $this->title;
    }
}
