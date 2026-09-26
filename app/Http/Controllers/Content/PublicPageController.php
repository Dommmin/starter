<?php

namespace App\Http\Controllers\Content;

use App\Data\Content\PublicPageData;
use App\Http\Controllers\Controller;
use App\Repositories\Content\PageRepository;
use App\Services\Content\RichTextRenderer;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicPageController extends Controller
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly RichTextRenderer $richText,
        private readonly LocalizationConfig $config,
        private readonly LocalizationManager $localization,
        private readonly LocalizedUrlGenerator $urls,
    ) {}

    /**
     * Show a published page translation in the current public locale.
     * A former slug of a published translation answers with a single 301 to
     * its current URL in the same locale. Drafts, missing translations and
     * unknown slugs are indistinguishable 404s.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $locale = $this->localization->getCurrentLocale($request);
        $slug = (string) $request->route('slug');

        $translation = $this->pages->findPublishedTranslation($locale, $slug);

        if ($translation === null) {
            $target = $this->pages->findPublishedRedirectTarget($locale, $slug);

            abort_if($target === null, 404);

            return redirect()->to(
                $this->urls->url('pages.show', ['slug' => $target->slug], $target->locale),
                301,
            );
        }

        $alternates = [];
        foreach ($this->config->getPublicLocales() as $alternateLocale) {
            $alternate = $translation->page->translation($alternateLocale);

            if ($alternate !== null) {
                $alternates[$alternateLocale] = $this->urls->url('pages.show', ['slug' => $alternate->slug], $alternateLocale);
            }
        }

        $default = $this->config->getPublicDefault();
        if (isset($alternates[$default])) {
            $alternates['x-default'] = $alternates[$default];
        }

        Inertia::share('i18n.alternateUrls', $alternates);

        return Inertia::render('pages/show', new PublicPageData(
            title: $translation->title,
            metaDescription: $translation->meta_description,
            bodyHtml: $this->richText->toHtml($translation->body),
            locale: $translation->locale,
            publishedAt: $translation->published_at?->toIso8601String(),
            alternates: $alternates,
        ));
    }
}
