<?php

namespace App\Actions\Navigation;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the two-level menu structure inside the caller's transaction:
 * the parent is a locked first-level item of the same menu, a group stays
 * on the first level, and an item with children never becomes a child.
 */
final class GuardMenuItemParent
{
    /**
     * @param  MenuItem|null  $item  The item being moved (null when creating).
     *
     * @throws ValidationException With a `parent_id` error.
     */
    public function handle(?int $parentId, MenuLocation $location, string $locale, MenuItemType $type, ?MenuItem $item = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($type === MenuItemType::Group) {
            self::fail('validation.navigation.group_top_level');
        }

        if ($item !== null && $parentId === $item->id) {
            self::fail('validation.navigation.parent_scope');
        }

        $parent = MenuItem::query()->whereKey($parentId)->lockForUpdate()->first();

        if ($parent === null || $parent->location !== $location || $parent->locale !== $locale) {
            self::fail('validation.navigation.parent_scope');
        }

        if ($parent->parent_id !== null) {
            self::fail('validation.navigation.max_depth');
        }

        if ($item !== null && $item->children()->exists()) {
            self::fail('validation.navigation.max_depth');
        }
    }

    /**
     * Position after the last sibling under the parent (1 for the first).
     */
    public function nextPosition(MenuLocation $location, string $locale, ?int $parentId): int
    {
        $last = MenuItem::query()
            ->where('location', $location->value)
            ->where('locale', $locale)
            ->where('parent_id', $parentId)
            ->lockForUpdate()
            ->max('position');

        return (is_numeric($last) ? (int) $last : 0) + 1;
    }

    /**
     * @throws ValidationException
     */
    private static function fail(string $key): never
    {
        throw ValidationException::withMessages(['parent_id' => __($key)]);
    }
}
