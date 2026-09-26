<?php

namespace App\Support\Listing;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Explicit, per-resource definition of a server-side list: searchable columns,
 * sort allowlist, filter allowlist and page size.
 *
 * One definition feeds the FormRequest rules, the Eloquent query and the
 * uniform Inertia payload `{ items, pagination, filters }`. Only allowlisted
 * column names ever reach SQL; request values are bound as parameters.
 */
final class ListQuery
{
    private const RESERVED_KEYS = ['search', 'sort', 'direction', 'page'];

    private const LIKE_ESCAPE = '!';

    /** @var list<literal-string> */
    private array $searchColumns = [];

    /** @var list<string> */
    private array $sortColumns = [];

    private ?string $defaultSort = null;

    /** @var 'asc'|'desc' */
    private string $defaultDirection = 'asc';

    /**
     * @var array<string, array{values: list<string>, default: string, apply: Closure(Builder<covariant Model>, string): void}>
     */
    private array $filters = [];

    private int $perPage = 15;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Columns matched with `LIKE %term%` (case sensitivity follows the
     * database collation). Names must be literal identifiers such as
     * `name` or `users.email`; they are never taken from the request.
     *
     * @param  literal-string  ...$columns
     */
    public function searchable(string ...$columns): self
    {
        foreach ($columns as $column) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column) !== 1) {
                throw new InvalidArgumentException("Searchable column [{$column}] is not a plain identifier.");
            }
        }

        $this->searchColumns = array_values($columns);

        return $this;
    }

    /**
     * @param  list<string>  $columns  Column names allowed in `sort`.
     * @param  'asc'|'desc'  $defaultDirection
     */
    public function sortable(array $columns, string $default, string $defaultDirection = 'asc'): self
    {
        if (! in_array($default, $columns, true)) {
            throw new InvalidArgumentException("Default sort [{$default}] must be one of the sortable columns.");
        }

        $this->sortColumns = $columns;
        $this->defaultSort = $default;
        $this->defaultDirection = $defaultDirection;

        return $this;
    }

    /**
     * Register an allowlisted filter. `$apply` always receives the resolved
     * value (the default when the request omits it) and decides the constraint.
     *
     * @param  list<string>  $values
     * @param  Closure(Builder<covariant Model>, string): void  $apply
     */
    public function filter(string $name, array $values, string $default, Closure $apply): self
    {
        if (in_array($name, self::RESERVED_KEYS, true)) {
            throw new InvalidArgumentException("Filter name [{$name}] is reserved.");
        }

        if (! in_array($default, $values, true)) {
            throw new InvalidArgumentException("Default value [{$default}] of filter [{$name}] must be an allowed value.");
        }

        $this->filters[$name] = ['values' => $values, 'default' => $default, 'apply' => $apply];

        return $this;
    }

    public function perPage(int $perPage): self
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Page size must be between 1 and 100.');
        }

        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Validation rules for the list query string.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'sort' => ['nullable', 'string', Rule::in($this->sortColumns)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];

        if ($this->searchColumns !== []) {
            $rules['search'] = ['nullable', 'string', 'max:255'];
        }

        foreach ($this->filters as $name => $filter) {
            $rules[$name] = ['nullable', 'string', Rule::in($filter['values'])];
        }

        return $rules;
    }

    /**
     * Resolve validated input into the effective list state with defaults.
     *
     * @param  array<string, mixed>  $validated
     * @return array{search: string, sort: string, direction: 'asc'|'desc', page: int, filters: array<string, string>}
     */
    public function state(array $validated): array
    {
        $sort = $validated['sort'] ?? null;
        $direction = $validated['direction'] ?? null;
        $page = $validated['page'] ?? 1;
        $search = $validated['search'] ?? '';

        $filters = [];
        foreach ($this->filters as $name => $filter) {
            $value = $validated[$name] ?? null;
            $filters[$name] = is_string($value) && in_array($value, $filter['values'], true)
                ? $value
                : $filter['default'];
        }

        return [
            'search' => $this->searchColumns === [] || ! is_string($search) ? '' : trim($search),
            'sort' => is_string($sort) && in_array($sort, $this->sortColumns, true)
                ? $sort
                : (string) $this->defaultSort,
            'direction' => $direction === 'asc' || $direction === 'desc' ? $direction : $this->defaultDirection,
            'page' => is_numeric($page) ? max(1, (int) $page) : 1,
            'filters' => $filters,
        ];
    }

    /**
     * Apply search, filters and a stable sort to the query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $validated
     * @return Builder<TModel>
     */
    public function apply(Builder $query, array $validated): Builder
    {
        $state = $this->state($validated);

        if ($state['search'] !== '') {
            $pattern = '%'.$this->escapeLike(mb_strtolower($state['search'])).'%';

            $query->where(function (Builder $inner) use ($pattern): void {
                foreach ($this->searchColumns as $column) {
                    $inner->orWhereRaw('lower('.$column.") like ? escape '".self::LIKE_ESCAPE."'", [$pattern]);
                }
            });
        }

        foreach ($this->filters as $name => $filter) {
            ($filter['apply'])($query, $state['filters'][$name]);
        }

        if ($this->defaultSort !== null) {
            $query->orderBy($query->qualifyColumn($state['sort']), $state['direction']);

            $keyName = $query->getModel()->getKeyName();
            if ($state['sort'] !== $keyName) {
                $query->orderBy($query->getModel()->getQualifiedKeyName(), $state['direction']);
            }
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $validated
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, array $validated): LengthAwarePaginator
    {
        return $this->apply($query, $validated)
            ->paginate($this->perPage, ['*'], 'page', $this->state($validated)['page']);
    }

    /**
     * Build the uniform list payload for an Inertia page.
     *
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
     * @param  Closure(TItem): array<string, mixed>  $mapItem
     * @param  array<string, mixed>  $validated
     * @return array{items: list<array<string, mixed>>, pagination: array{page: int, totalPages: int, total: int, perPage: int}, filters: array<string, string>}
     */
    public function payload(LengthAwarePaginator $paginator, Closure $mapItem, array $validated): array
    {
        $state = $this->state($validated);

        $items = [];
        foreach ($paginator->items() as $item) {
            $items[] = $mapItem($item);
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'totalPages' => max($paginator->lastPage(), 1),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'filters' => [
                'search' => $state['search'],
                'sort' => $state['sort'],
                'direction' => $state['direction'],
                ...$state['filters'],
            ],
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $value,
        );
    }
}
