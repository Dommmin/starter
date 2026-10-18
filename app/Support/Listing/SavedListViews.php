<?php

namespace App\Support\Listing;

use Illuminate\Support\Facades\Route;

/**
 * Rules of saved admin list views: which lists accept them and which query
 * parameters are kept (never the page number).
 */
final class SavedListViews
{
    /** Views per user and list. */
    public const int MAX_PER_LIST = 20;

    public static function isList(string $name): bool
    {
        return preg_match('/^admin\.[a-z0-9-]+\.index$/', $name) === 1 && Route::has($name);
    }

    /**
     * Keys of snake_case parameters with a non-empty value, sorted.
     *
     * @param  array<array-key, string|null>  $query
     * @return array<string, string>
     */
    public static function cleanQuery(array $query): array
    {
        $clean = [];

        foreach ($query as $key => $value) {
            if (is_string($key) && $key !== 'page' && preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) === 1 && $value !== null && $value !== '') {
                $clean[$key] = $value;
            }
        }

        ksort($clean);

        return $clean;
    }
}
