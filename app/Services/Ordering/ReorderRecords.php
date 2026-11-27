<?php

namespace App\Services\Ordering;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared guard for storing a new manual order of one group of records (the
 * sections of a locale, the siblings of a menu level). In one transaction it
 * locks the whole group, checks that the submitted ids are exactly the
 * group's current records (no foreign, missing or duplicate id; an empty
 * group is never valid) and only then hands the locked records to the
 * caller, which writes positions and audits in the same transaction. A
 * stale or forged set raises a `conflict` error and nothing is written.
 *
 * The model's order column is `position`; records are read in that order
 * (then by id). The lock is a plain `SELECT ... FOR UPDATE` of the rows,
 * without aggregates, so it works on SQLite, MySQL and PostgreSQL alike.
 */
class ReorderRecords
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $scope  Columns that define the group; a null value matches `IS NULL`.
     * @param  list<int>  $ids  Submitted new order, first shown first.
     * @param  string  $conflictMessage  Translated message of the `conflict` error.
     * @param  Closure(Collection<int, TModel>, list<int>): void  $apply  Receives the locked records keyed by id and their current order of ids.
     *
     * @throws ValidationException When `ids` is not exactly the group's current set (`conflict`).
     */
    public function handle(
        string $model,
        array $scope,
        array $ids,
        string $conflictMessage,
        Closure $apply,
    ): void {
        DB::transaction(function () use ($model, $scope, $ids, $conflictMessage, $apply): void {
            $query = $model::query();

            foreach ($scope as $column => $value) {
                $query->where($column, $value);
            }

            /** @var Collection<int, TModel> $records */
            $records = $query
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $current = array_values($records->keys()->map(intval(...))->all());
            $expected = $current;
            $submitted = $ids;
            sort($expected);
            sort($submitted);

            if ($records->isEmpty() || $expected !== $submitted) {
                throw ValidationException::withMessages([
                    'conflict' => $conflictMessage,
                ]);
            }

            $apply($records, $current);
        });
    }
}
