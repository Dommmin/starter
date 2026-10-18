<?php

namespace App\Contracts\Search;

use App\Data\Admin\Search\AdminSearchResultData;
use Illuminate\Database\Eloquent\Model;

/**
 * A module whose records the admin command palette (⌘K) can find. Register
 * the class in App\Providers\AdminSearchServiceProvider. Results are shown
 * only to users who may list the module (`viewAny` on `model()`), so a
 * source never checks permissions itself.
 */
interface AdminSearchSource
{
    /**
     * Model class whose `viewAny` ability guards the results.
     *
     * @return class-string<Model>
     */
    public function model(): string;

    /**
     * Records matching the term (case-insensitive substring), newest first.
     *
     * @return list<AdminSearchResultData>
     */
    public function search(string $term, int $limit): array;
}
