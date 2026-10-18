<?php

namespace App\Support\Import;

use App\Contracts\Import\ImportDefinition;
use App\Http\Requests\Admin\Faqs\StoreFaqRequest;
use App\Models\Faq;

/**
 * FAQ import: `question,answer,locale,position,published`. An empty locale
 * shows the faq in every language; `published` accepts 1/0, yes/no, tak/nie.
 */
final class FaqImport implements ImportDefinition
{
    public function key(): string
    {
        return 'faqs';
    }

    public function model(): string
    {
        return Faq::class;
    }

    public function columns(): array
    {
        return ['question', 'answer', 'locale', 'position', 'published'];
    }

    public function requiredColumns(): array
    {
        return ['question', 'answer'];
    }

    public function normalize(array $row): array
    {
        return [
            'question' => CsvValue::text($row['question'] ?? null),
            'answer' => CsvValue::text($row['answer'] ?? null),
            'locale' => CsvValue::text($row['locale'] ?? null),
            'position' => CsvValue::integer($row['position'] ?? null),
            'published' => CsvValue::boolean($row['published'] ?? null, default: true),
        ];
    }

    public function rules(): array
    {
        return [
            ...StoreFaqRequest::fieldRules(),
            'question' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    public function label(array $row): string
    {
        return (string) ($row['question'] ?? '');
    }

    public function store(array $row): void
    {
        Faq::query()->create($row);
    }
}
