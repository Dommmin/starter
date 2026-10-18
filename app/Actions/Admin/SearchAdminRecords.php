<?php

namespace App\Actions\Admin;

use App\Contracts\Search\AdminSearchSource;
use App\Data\Admin\Search\AdminSearchResultData;
use App\Models\User;

/**
 * Search every registered AdminSearchSource the user may list (`viewAny`
 * on the source model) for the command palette.
 */
class SearchAdminRecords
{
    /** Results per source; the palette shows each type as one group. */
    public const int LIMIT_PER_SOURCE = 5;

    /**
     * @param  iterable<AdminSearchSource>  $sources
     */
    public function __construct(private readonly iterable $sources) {}

    /**
     * @return list<AdminSearchResultData>
     */
    public function handle(User $user, string $term): array
    {
        $results = [];

        foreach ($this->sources as $source) {
            if ($user->can('viewAny', $source->model())) {
                array_push($results, ...$source->search($term, self::LIMIT_PER_SOURCE));
            }
        }

        return $results;
    }
}
