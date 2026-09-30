<?php

namespace App\Support\DemoContent;

use App\Actions\Navigation\DeleteMenuItem;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Localization\LocalizationConfig;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample menu items of {@see NavigationMenuSeeder::defaults()}: a first-level
 * item matches when its menu (location + locale), position, type and target
 * (anchor / page) are exactly those of the seeded definition. It counts as
 * edited when its label, URL, new-tab flag, author, children or timestamps
 * differ from the seeded state.
 */
class DemoMenuItems implements DemoContentProvider
{
    public function __construct(
        private readonly DeleteMenuItem $deleteMenuItem,
        private readonly LocalizationConfig $localization,
    ) {}

    public function label(): string
    {
        return 'menu items';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        $privacyPageId = NavigationMenuSeeder::privacyPolicyPageId();
        $records = [];

        foreach ($this->localization->getPublicLocales() as $locale) {
            foreach (NavigationMenuSeeder::defaults($locale, $privacyPageId) as $location => $items) {
                foreach ($items as $index => $definition) {
                    $item = MenuItem::query()
                        ->where('location', MenuLocation::from($location)->value)
                        ->where('locale', $locale)
                        ->whereNull('parent_id')
                        ->where('position', $index + 1)
                        ->where('type', $definition['type']->value)
                        ->where('anchor', $definition['anchor'] ?? null)
                        ->where('page_id', $definition['page_id'] ?? null)
                        ->whereNull('article_id')
                        ->first();

                    if ($item !== null) {
                        $records[] = $item;
                    }
                }
            }
        }

        return $records;
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        $item = $this->item($record);
        $definition = NavigationMenuSeeder::defaults($item->locale, NavigationMenuSeeder::privacyPolicyPageId())[$item->location->value][$item->position - 1] ?? null;

        return $definition === null
            || $item->label !== ($definition['label'] ?? null)
            || $item->url !== null
            || $item->open_in_new_tab
            || $item->created_by !== null
            || $item->updated_by !== null
            || $item->children()->exists()
            || ($item->updated_at !== null && $item->created_at !== null && $item->updated_at->gt($item->created_at));
    }

    public function describe(Model $record): string
    {
        $item = $this->item($record);

        return sprintf('%s/%s #%d %s', $item->location->value, $item->locale, $item->position, $item->label ?? $item->type->value);
    }

    public function delete(Model $record, User $actor): void
    {
        $this->deleteMenuItem->handle($this->item($record), $actor);
    }

    private function item(Model $record): MenuItem
    {
        if (! $record instanceof MenuItem) {
            throw new InvalidArgumentException('Expected a menu item.');
        }

        return $record;
    }
}
