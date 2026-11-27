<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\DB;

/**
 * Free `{slug}-copy-N` slugs for copies of translated content (pages and
 * articles), one locale at a time.
 *
 * A slug counts as taken when any translation in the locale uses it,
 * including translations of records in the trash (they keep their unique
 * slug until the trash is emptied), or when it is the former slug of a
 * permanent redirect: a copy must never take over an existing redirect.
 */
class CopySlugs
{
    /** Slug columns are 200 characters; room is kept for `-copy-` and the number. */
    private const int MAX_BASE_LENGTH = 188;

    /**
     * The first free `{base}-copy-N` slug in the locale. A copy suffix of the
     * source is replaced, so copying a copy gives the next number instead of
     * a growing chain.
     *
     * @param  literal-string  $translationTable  Table with `locale` and `slug` columns.
     * @param  literal-string  $redirectTable  Table with `locale` and `old_slug` columns.
     */
    public function next(string $translationTable, string $redirectTable, string $locale, string $slug): string
    {
        $base = rtrim(substr((string) preg_replace('/-copy-\d+$/', '', $slug), 0, self::MAX_BASE_LENGTH), '-');
        $pattern = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $base).'-copy-%';

        $taken = DB::table($translationTable)
            ->where('locale', $locale)
            ->whereRaw("slug like ? escape '!'", [$pattern])
            ->pluck('slug')
            ->merge(
                DB::table($redirectTable)
                    ->where('locale', $locale)
                    ->whereRaw("old_slug like ? escape '!'", [$pattern])
                    ->pluck('old_slug'),
            )
            ->flip();

        $number = 1;
        while ($taken->has("{$base}-copy-{$number}")) {
            $number++;
        }

        return "{$base}-copy-{$number}";
    }
}
