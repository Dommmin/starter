<?php

namespace App\Support\Import;

use App\Contracts\Import\ImportDefinition;
use App\Http\Requests\Admin\Redirects\StoreRedirectRequest;
use App\Models\Redirect;
use App\Support\Redirects\RedirectPath;

/**
 * Redirect import (migration of a former site): `source` (path or full old
 * URL) and `target` are required; `status_code` defaults to 301 and
 * `is_active` to yes. Rows follow the editor's rules, sources must be new
 * and unique in the file, and no row may form a chain with another row or
 * a stored redirect.
 */
final class RedirectImport implements ImportDefinition
{
    public function key(): string
    {
        return 'redirects';
    }

    public function model(): string
    {
        return Redirect::class;
    }

    public function columns(): array
    {
        return ['source', 'target', 'status_code', 'is_active'];
    }

    public function requiredColumns(): array
    {
        return ['source', 'target'];
    }

    public function normalize(array $row): array
    {
        $source = CsvValue::text($row['source'] ?? null);
        $target = CsvValue::text($row['target'] ?? null);

        return [
            'source_path' => $source === null ? null : RedirectPath::source($source),
            'target' => $target === null ? null : RedirectPath::target($target),
            'status_code' => CsvValue::integer($row['status_code'] ?? null) ?? 301,
            'is_active' => CsvValue::boolean($row['is_active'] ?? null, true),
        ];
    }

    public function rules(): array
    {
        $rules = StoreRedirectRequest::fieldRules();
        $rules['source_path'][] = 'distinct';

        return $rules;
    }

    public function label(array $row): string
    {
        return (string) ($row['source_path'] ?? '');
    }

    public function store(array $row): void
    {
        Redirect::query()->create([
            'source_path' => $row['source_path'],
            'target' => $row['target'],
            'status_code' => $row['status_code'],
            'is_active' => $row['is_active'],
        ]);
    }
}
