<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * Case-insensitive substring search over columns, with `%`, `_` and the
 * escape character in the term matched literally (same rules as the admin
 * list search in App\Support\Listing\ListQuery).
 */
final class LikeSearch
{
    private const string ESCAPE = '!';

    /**
     * Limit the query to rows where any of the columns contains the term.
     * Columns are SQL expressions written by the caller, never user input.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<literal-string>  $columns
     */
    public static function apply(Builder $query, array $columns, string $term): void
    {
        $pattern = '%'.str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            mb_strtolower($term),
        ).'%';

        $query->where(function (Builder $inner) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw('lower('.$column.") like ? escape '".self::ESCAPE."'", [$pattern]);
            }
        });
    }
}
