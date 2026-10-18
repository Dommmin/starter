<?php

namespace App\Contracts\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * A module whose records administrators can import from CSV. Register the
 * class in App\Support\Import\ImportDefinitions. The importer validates all
 * rows at once (`distinct` catches duplicates inside the file) and stores
 * them in one transaction.
 */
interface ImportDefinition
{
    /**
     * Route key, e.g. `faqs`.
     */
    public function key(): string;

    /**
     * Model class whose `import` ability guards the import.
     *
     * @return class-string<Model>
     */
    public function model(): string;

    /**
     * Expected CSV header names in order; optional columns may be missing.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Header names that must be present.
     *
     * @return list<string>
     */
    public function requiredColumns(): array;

    /**
     * Normalized values of one row (trimmed strings, empty cells as null,
     * booleans and numbers converted where the column expects them).
     *
     * @param  array<string, string>  $row  Cells keyed by header name.
     * @return array<string, mixed>
     */
    public function normalize(array $row): array;

    /**
     * Validation rules of one normalized row (keys without the `rows.*.` prefix).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array;

    /**
     * Short text identifying a row in the preview, e.g. the question.
     *
     * @param  array<string, mixed>  $row
     */
    public function label(array $row): string;

    /**
     * Store one validated row.
     *
     * @param  array<string, mixed>  $row
     */
    public function store(array $row): void;
}
