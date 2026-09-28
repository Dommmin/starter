<?php

namespace App\Http\Requests\Admin\Articles;

use App\Data\Content\ArticleTranslationInputData;
use App\Enums\MediaStatus;
use App\Enums\PublicationStatus;
use App\Http\Requests\Admin\Pages\PageTranslationRules;
use App\Models\MediaAsset;
use App\Rules\RichTextDocument;
use App\Services\Content\RichTextRenderer;
use App\Services\Localization\LocalizationConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for the article payload shared by the store and update
 * requests: an optional DAM cover and per-locale `translations`.
 *
 * Every active public locale may be sent as `translations.{locale}`. An empty
 * title means "no translation for this locale" and skips the remaining
 * fields; the default public locale is always required. Article slugs live
 * under `/articles/`, so they only need to be unique per locale.
 */
final class ArticleInputRules
{
    public function __construct(
        private readonly LocalizationConfig $config,
        private readonly RichTextRenderer $renderer,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(FormRequest $request, ?int $ignoreArticleId = null): array
    {
        $locales = $this->config->getPublicLocales();
        $default = $this->config->getPublicDefault();

        $rules = [
            'cover_media_id' => [
                'nullable',
                'integer',
                Rule::exists('media_assets', 'id')->where(function (Builder $query): void {
                    $query->where('status', MediaStatus::Clean->value)
                        ->whereIn('mime', MediaAsset::IMAGE_MIMES)
                        ->whereNotNull('variants');
                }),
            ],
            'translations' => ['required', 'array:'.implode(',', $locales)],
        ];

        foreach ($locales as $locale) {
            $prefix = "translations.{$locale}";
            $isDefault = $locale === $default;
            $skip = Rule::excludeIf(fn (): bool => blank($request->input("{$prefix}.title")));

            $unique = Rule::unique('article_translations', 'slug')->where('locale', $locale);
            if ($ignoreArticleId !== null) {
                $unique->whereNot('article_id', $ignoreArticleId);
            }

            $rules[$prefix] = [$isDefault ? 'required' : 'nullable', 'array'];
            $rules["{$prefix}.title"] = [$isDefault ? 'required' : 'nullable', 'string', 'max:200'];
            $rules["{$prefix}.slug"] = [
                $skip,
                'required',
                'string',
                'max:200',
                'regex:'.PageTranslationRules::SLUG_PATTERN,
                $unique,
            ];
            $rules["{$prefix}.excerpt"] = [$skip, 'nullable', 'string', 'max:500'];
            $rules["{$prefix}.meta_description"] = [$skip, 'nullable', 'string', 'max:320'];
            $rules["{$prefix}.body"] = [$skip, 'nullable', 'array', new RichTextDocument($this->renderer)];
            $rules["{$prefix}.status"] = [$skip, 'required', Rule::enum(PublicationStatus::class)];
            $rules["{$prefix}.published_on"] = [$skip, 'nullable', 'date_format:Y-m-d'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function coverMediaId(array $validated): ?int
    {
        $cover = $validated['cover_media_id'] ?? null;

        return $cover === null ? null : (int) $cover;
    }

    /**
     * Map validated input to action input, keyed by locale. Locales with an
     * empty title are omitted.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, ArticleTranslationInputData>
     */
    public function toInput(array $validated): array
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = $validated['translations'] ?? [];
        $input = [];

        foreach ($this->config->getPublicLocales() as $locale) {
            $translation = $translations[$locale] ?? null;

            if (! is_array($translation) || blank($translation['title'] ?? null)) {
                continue;
            }

            /** @var array<string, mixed>|null $body */
            $body = $translation['body'] ?? null;
            $publishedOn = $translation['published_on'] ?? null;

            $input[$locale] = new ArticleTranslationInputData(
                title: (string) $translation['title'],
                slug: (string) $translation['slug'],
                excerpt: isset($translation['excerpt']) ? (string) $translation['excerpt'] : null,
                metaDescription: isset($translation['meta_description']) ? (string) $translation['meta_description'] : null,
                body: $body,
                status: PublicationStatus::from((string) $translation['status']),
                publishedOn: is_string($publishedOn) ? CarbonImmutable::createFromFormat('!Y-m-d', $publishedOn) ?: null : null,
            );
        }

        return $input;
    }

    /**
     * Whether any submitted translation asks to be published.
     */
    public function requestsPublication(FormRequest $request): bool
    {
        $translations = $request->input('translations');

        if (! is_array($translations)) {
            return false;
        }

        foreach ($translations as $translation) {
            if (is_array($translation)
                && ! blank($translation['title'] ?? null)
                && ($translation['status'] ?? null) === PublicationStatus::Published->value) {
                return true;
            }
        }

        return false;
    }
}
