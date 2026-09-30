<?php

namespace App\Support\DemoContent;

use App\Actions\Navigation\DeleteMenuItem;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\DemoNavigationSeeder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample top-level menu items of {@see DemoNavigationSeeder} (with their
 * submenus), matched by menu, type and label. An item counts as edited when
 * it or a child has an author or changed after creation, or when its
 * children differ in number from the seeded submenu. Deleting a group
 * removes its children through the audited menu action.
 */
class DemoNavigationItems implements DemoContentProvider
{
    public function __construct(private readonly DeleteMenuItem $deleteMenuItem) {}

    public function label(): string
    {
        return 'sample menu items';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        $records = [];

        foreach (DemoNavigationSeeder::LOCALES as $locale) {
            foreach (DemoNavigationSeeder::definitions($locale) as $location => $items) {
                foreach ($items as $definition) {
                    $item = DemoNavigationSeeder::find($location, $locale, $definition);

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
        $definition = collect(DemoNavigationSeeder::definitions($item->locale)[$item->location->value] ?? [])
            ->first(fn (array $candidate): bool => $candidate['type'] === $item->type && $candidate['label'] === $item->label);
        $children = $item->children()->get();

        return $definition === null
            || $children->count() !== count($definition['children'] ?? [])
            || collect([$item, ...$children->all()])->contains(fn (MenuItem $model): bool => $model->created_by !== null
                || $model->updated_by !== null
                || ($model->updated_at !== null && $model->created_at !== null && $model->updated_at->gt($model->created_at)));
    }

    public function describe(Model $record): string
    {
        $item = $this->item($record);

        return sprintf('%s/%s %s', $item->location->value, $item->locale, $item->label);
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
