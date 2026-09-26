<?php

namespace App\Http\Requests\Admin\Pages;

use App\Data\Content\PageTranslationInputData;
use App\Enums\PublicationStatus;
use App\Rules\RichTextDocument;
use App\Services\Content\RichTextRenderer;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Validation rules for the per-locale `translations` payload shared by the
 * store and update page requests.
 *
 * Every active public locale may be sent as `translations.{locale}`. An empty
 * title means "no translation for this locale" and skips the remaining
 * fields; the default public locale is always required.
 */
final class PageTranslationRules
{
    /** Lowercase kebab-case; also used as the `{slug}` route constraint. */
    public const SLUG_ROUTE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public const SLUG_PATTERN = '/^'.self::SLUG_ROUTE_PATTERN.'$/';

    /**
     * Top-level paths that are served by the application or the web server
     * outside the router and therefore can never be used as a page slug.
     *
     * @var list<string>
     */
    private const EXTRA_RESERVED_SLUGS = ['api', 'build', 'storage', 'up', 'vendor'];

    public function __construct(
        private readonly LocalizationConfig $config,
        private readonly RichTextRenderer $renderer,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(FormRequest $request, ?int $ignorePageId = null): array
    {
        $locales = $this->config->getPublicLocales();
        $default = $this->config->getPublicDefault();
        $reserved = $this->reservedSlugs();

        $rules = [
            'translations' => ['required', 'array:'.implode(',', $locales)],
        ];

        foreach ($locales as $locale) {
            $prefix = "translations.{$locale}";
            $isDefault = $locale === $default;
            $skip = Rule::excludeIf(fn (): bool => blank($request->input("{$prefix}.title")));

            $unique = Rule::unique('page_translations', 'slug')->where('locale', $locale);
            if ($ignorePageId !== null) {
                $unique->whereNot('page_id', $ignorePageId);
            }

            $rules[$prefix] = [$isDefault ? 'required' : 'nullable', 'array'];
            $rules["{$prefix}.title"] = [$isDefault ? 'required' : 'nullable', 'string', 'max:200'];
            $rules["{$prefix}.slug"] = [
                $skip,
                'required',
                'string',
                'max:200',
                'regex:'.self::SLUG_PATTERN,
                Rule::notIn($reserved),
                $unique,
            ];
            $rules["{$prefix}.meta_description"] = [$skip, 'nullable', 'string', 'max:320'];
            $rules["{$prefix}.body"] = [$skip, 'nullable', 'array', new RichTextDocument($this->renderer)];
            $rules["{$prefix}.status"] = [$skip, 'required', Rule::enum(PublicationStatus::class)];
        }

        return $rules;
    }

    /**
     * Map validated input to action input, keyed by locale. Locales with an
     * empty title are omitted.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, PageTranslationInputData>
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

            $input[$locale] = new PageTranslationInputData(
                title: (string) $translation['title'],
                slug: (string) $translation['slug'],
                metaDescription: isset($translation['meta_description']) ? (string) $translation['meta_description'] : null,
                body: $body,
                status: PublicationStatus::from((string) $translation['status']),
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

    /**
     * First path segments owned by other routes (login, admin, settings,
     * about, ...) and every registered locale code, so a page can never be
     * created at an address it could not be served from.
     *
     * @return list<string>
     */
    public function reservedSlugs(): array
    {
        $reserved = [...self::EXTRA_RESERVED_SLUGS, ...array_keys($this->config->getRegistry())];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if ($name === 'pages.show' || $name === 'localized.pages.show') {
                continue;
            }

            $segment = explode('/', trim($route->uri(), '/'))[0];

            if ($segment !== '' && ! str_starts_with($segment, '{')) {
                $reserved[] = strtolower($segment);
            }
        }

        return array_values(array_unique($reserved));
    }
}
