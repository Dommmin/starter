<?php

namespace App\Http\Requests\Admin\Tags;

use App\Models\Tag;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or rename a tag. The slug is shared by every locale (public
 * filter `?tag=`); the name is required in the default public locale.
 */
class SaveTagRequest extends FormRequest
{
    private const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? ($this->user()?->can('update', $tag) ?? false)
            : ($this->user()?->can('create', Tag::class) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $tag = $this->route('tag');
        $locales = $this->localization()->getPublicLocales();
        $default = $this->localization()->getPublicDefault();

        $rules = [
            'updated_at' => ['nullable', 'string', 'date'],
            'slug' => ['required', 'string', 'max:60', 'regex:'.self::SLUG_PATTERN, Rule::unique('tags', 'slug')->ignore($tag instanceof Tag ? $tag->id : null)],
            'name' => ['required', 'array:'.implode(',', $locales)],
        ];

        foreach ($locales as $locale) {
            $rules["name.{$locale}"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:60', 'not_regex:/\\p{Cc}/u'];
        }

        return $rules;
    }

    public function expectedUpdatedAt(): ?string
    {
        $updatedAt = $this->validated('updated_at');

        return is_string($updatedAt) ? $updatedAt : null;
    }

    /**
     * Slug and the non-empty, trimmed names keyed by locale.
     *
     * @return array{slug: string, name: array<string, string>}
     */
    public function tagValues(): array
    {
        $validated = $this->validated();

        return [
            'slug' => $validated['slug'],
            'name' => array_filter(
                array_map(fn (mixed $value): string => trim((string) $value), $validated['name']),
                fn (string $value): bool => $value !== '',
            ),
        ];
    }

    private function localization(): LocalizationConfig
    {
        return app(LocalizationConfig::class);
    }
}
