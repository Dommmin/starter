<?php

namespace App\Support\Import;

use App\Contracts\Import\ImportDefinition;
use App\Models\Tag;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Validation\Rule;

/**
 * Tag import: `slug` and `name_{locale}` per public locale; the name in the
 * default locale is required, slugs must be new and unique in the file.
 */
final class TagImport implements ImportDefinition
{
    public function __construct(private readonly LocalizationConfig $localization) {}

    public function key(): string
    {
        return 'tags';
    }

    public function model(): string
    {
        return Tag::class;
    }

    public function columns(): array
    {
        return ['slug', ...array_map(fn (string $locale): string => "name_{$locale}", $this->localization->getPublicLocales())];
    }

    public function requiredColumns(): array
    {
        return ['slug', 'name_'.$this->localization->getPublicDefault()];
    }

    public function normalize(array $row): array
    {
        $values = ['slug' => CsvValue::text($row['slug'] ?? null)];

        foreach ($this->localization->getPublicLocales() as $locale) {
            $values["name_{$locale}"] = CsvValue::text($row["name_{$locale}"] ?? null);
        }

        return $values;
    }

    public function rules(): array
    {
        $default = $this->localization->getPublicDefault();
        $rules = [
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'distinct', Rule::unique('tags', 'slug')],
        ];

        foreach ($this->localization->getPublicLocales() as $locale) {
            $rules["name_{$locale}"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:60', 'not_regex:/\p{Cc}/u'];
        }

        return $rules;
    }

    public function label(array $row): string
    {
        return (string) ($row['slug'] ?? '');
    }

    public function store(array $row): void
    {
        $names = [];
        foreach ($this->localization->getPublicLocales() as $locale) {
            if (($row["name_{$locale}"] ?? null) !== null) {
                $names[$locale] = $row["name_{$locale}"];
            }
        }

        Tag::query()->create(['slug' => $row['slug'], 'name' => $names]);
    }
}
