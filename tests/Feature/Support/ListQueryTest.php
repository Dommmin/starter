<?php

use App\Models\User;
use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

function userListQuery(): ListQuery
{
    return ListQuery::make()
        ->searchable('name', 'email')
        ->sortable(['name', 'created_at'], default: 'created_at', defaultDirection: 'desc')
        ->filter('verified', ['all', 'verified', 'unverified'], default: 'all', apply: function (Builder $query, string $value): void {
            if ($value === 'verified') {
                $query->whereNotNull('email_verified_at');
            }
        })
        ->perPage(2);
}

test('rules reject values outside the sort, direction and filter allowlists', function () {
    $validator = Validator::make([
        'sort' => 'password',
        'direction' => 'sideways',
        'verified' => 'maybe',
        'page' => 0,
    ], userListQuery()->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toEqualCanonicalizing(['sort', 'direction', 'verified', 'page']);
});

test('search is only accepted when the list declares searchable columns', function () {
    $rules = ListQuery::make()->sortable(['name'], default: 'name')->rules();

    expect($rules)->not->toHaveKey('search');
});

test('state resolves defaults when the query string is empty', function () {
    expect(userListQuery()->state([]))->toBe([
        'search' => '',
        'sort' => 'created_at',
        'direction' => 'desc',
        'page' => 1,
        'filters' => ['verified' => 'all'],
    ]);
});

test('search escapes like wildcards so they match literally', function () {
    User::factory()->create(['name' => 'Percent 100% match']);
    User::factory()->create(['name' => 'Percent 1000 match']);
    User::factory()->create(['name' => 'under_score']);
    User::factory()->create(['name' => 'underXscore']);

    $percent = userListQuery()->apply(User::query(), ['search' => '100%'])->pluck('name')->all();
    $underscore = userListQuery()->apply(User::query(), ['search' => 'under_'])->pluck('name')->all();

    expect($percent)->toBe(['Percent 100% match'])
        ->and($underscore)->toBe(['under_score']);
});

test('filters receive the resolved value and sorting is stable', function () {
    User::factory()->create(['name' => 'Same']);
    User::factory()->create(['name' => 'Same']);
    User::factory()->unverified()->create(['name' => 'Alpha']);

    $query = userListQuery()->apply(User::query(), ['verified' => 'verified', 'sort' => 'name', 'direction' => 'asc']);

    $ids = $query->pluck('id')->all();
    expect($ids)->toBe(User::query()->whereNotNull('email_verified_at')->orderBy('id')->pluck('id')->all());
});

test('payload exposes items, pagination and effective filters', function () {
    User::factory()->count(3)->create();
    $listQuery = userListQuery();
    $validated = ['page' => 2, 'verified' => 'verified'];

    $payload = $listQuery->payload(
        $listQuery->paginate(User::query(), $validated),
        fn (User $user): array => ['id' => $user->id],
        $validated,
    );

    expect($payload['pagination'])->toBe(['page' => 2, 'totalPages' => 2, 'total' => 3, 'perPage' => 2])
        ->and($payload['items'])->toHaveCount(1)
        ->and($payload['filters'])->toBe([
            'search' => '',
            'sort' => 'created_at',
            'direction' => 'desc',
            'verified' => 'verified',
        ]);
});

test('definitions reject defaults outside their allowlists and reserved filter names', function () {
    expect(fn () => ListQuery::make()->sortable(['name'], default: 'email'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ListQuery::make()->filter('status', ['a'], 'b', fn () => null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ListQuery::make()->filter('page', ['a'], 'a', fn () => null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ListQuery::make()->searchable('name) or (1=1'))->toThrow(InvalidArgumentException::class);
});

test('search ignores letter case on every database driver', function () {
    User::factory()->create(['name' => 'Zofia Nowak']);
    User::factory()->create(['name' => 'Adam Kowalski']);

    $names = userListQuery()->apply(User::query(), ['search' => 'NOWAK'])->pluck('name')->all();

    expect($names)->toBe(['Zofia Nowak']);
});
