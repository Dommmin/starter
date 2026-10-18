<?php

namespace App\Repositories\Users;

use App\Contracts\Search\AdminSearchSource;
use App\Data\Admin\Search\AdminSearchResultData;
use App\Models\User;
use App\Support\Search\LikeSearch;

/**
 * Named read queries of user accounts.
 */
class UserRepository implements AdminSearchSource
{
    public function model(): string
    {
        return User::class;
    }

    /**
     * Accounts whose name or e-mail contains the term.
     */
    public function search(string $term, int $limit): array
    {
        $query = User::query()->orderBy('name')->orderBy('id');
        LikeSearch::apply($query, ['name', 'email'], $term);

        $results = [];
        foreach ($query->limit($limit)->get() as $user) {
            $results[] = new AdminSearchResultData(
                type: 'users',
                id: $user->id,
                label: $user->name,
                description: $user->email,
                url: route('admin.users.edit', $user),
            );
        }

        return $results;
    }
}
