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

test('a mapped sort key orders by the mapped column', function () {
    $listQuery = ListQuery::make()
        ->sortable(['label', 'id'], default: 'label', columnMap: ['label' => 'users.name']);

    $sql = $listQuery->apply(User::query(), ['sort' => 'label'])->toSql();

    expect($sql)->toContain('order by "users"."name" asc');
});

test('sort column mappings must be plain identifiers of declared sort keys', function (array $columnMap) {
    ListQuery::make()->sortable(['label'], default: 'label', columnMap: $columnMap);
})->with([
    [['label' => 'name; drop table users']],
    [['other' => 'users.name']],
])->throws(InvalidArgumentException::class);

function datedUserListQuery(): ListQuery
{
    return ListQuery::make()
        ->sortable(['created_at'], default: 'created_at')
        ->dateRange('created', 'created_at');
}

test('a date range accepts either bound and rejects malformed or inverted dates', function (array $input, array $errors) {
    $validator = Validator::make($input, datedUserListQuery()->rules());

    expect($validator->errors()->keys())->toEqualCanonicalizing($errors);
})->with([
    'both empty' => [[], []],
    'only from' => [['created_from' => '2026-10-01'], []],
    'only to' => [['created_to' => '2026-10-01'], []],
    'same day' => [['created_from' => '2026-10-01', 'created_to' => '2026-10-01'], []],
    'not a date' => [['created_from' => '01.10.2026', 'created_to' => '2026-02-30'], ['created_from', 'created_to']],
    'inverted' => [['created_from' => '2026-10-02', 'created_to' => '2026-10-01'], ['created_to']],
]);

test('a date range filters inclusively by calendar day and echoes empty bounds', function () {
    User::factory()->create(['created_at' => '2026-09-30 23:59:59']);
    $first = User::factory()->create(['created_at' => '2026-10-01 00:00:00']);
    $last = User::factory()->create(['created_at' => '2026-10-03 23:59:59']);
    $after = User::factory()->create(['created_at' => '2026-10-04 00:00:00']);
    $listQuery = datedUserListQuery();

    $range = $listQuery->apply(User::query(), ['created_from' => '2026-10-01', 'created_to' => '2026-10-03'])->pluck('id')->all();
    $openEnded = $listQuery->apply(User::query(), ['created_from' => '2026-10-04'])->pluck('id')->all();

    expect($range)->toBe([$first->id, $last->id])
        ->and($openEnded)->toBe([$after->id])
        ->and($listQuery->state(['created_to' => '2026-10-03'])['filters'])
        ->toBe(['created_from' => '', 'created_to' => '2026-10-03']);
});

test('date range names cannot collide and columns must be identifiers', function () {
    expect(fn () => ListQuery::make()->dateRange('created', 'created_at; drop table users'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => ListQuery::make()
        ->filter('created_from', ['all'], default: 'all', apply: fn () => null)
        ->dateRange('created', 'created_at'))
        ->toThrow(InvalidArgumentException::class);
});
