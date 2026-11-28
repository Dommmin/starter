<?php

namespace App\Services\Content;

use App\Actions\Home\BuildHomeSections;
use App\Data\Contact\ContactFormData;
use App\Data\Content\ContentPreviewData;
use App\Data\Content\PublicArticleData;
use App\Data\Content\PublicPageData;
use App\Data\Content\PublicTagData;
use App\Data\Home\ContactContentData;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Models\ArticleTranslation;
use App\Models\PageTranslation;
use App\Models\User;
use App\Repositories\Contact\ContactFormRepository;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use App\Services\Navigation\PublicNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the saved translation of a page or article, in any publication
 * state, on the public screen with a preview banner. Never indexable or
 * cacheable. Callers authorize the request first.
 */
class ContentPreviewRenderer
{
    public function __construct(
        private readonly RichTextRenderer $richText,
        private readonly LocalizationConfig $config,
        private readonly LocalizationManager $localization,
        private readonly LocalizedUrlGenerator $urls,
        private readonly PublicNavigation $navigation,
        private readonly BuildHomeSections $buildSections,
        private readonly ContactFormRepository $contactForms,
    ) {}

    /**
     * @param  User|null  $viewer  Decides which links inside sections are shown.
     */
    public function page(Request $request, PageTranslation $translation, ContentPreviewData $preview, ?User $viewer): Response
    {
        $locale = $translation->locale;
        $this->usePublicContext($request, $locale);

        $page = $translation->page()->firstOrFail();
        $layoutMode = $page->layout_mode ?: 'rich_text';
        $sections = null;
        $contactForm = null;

        if ($layoutMode === 'sections') {
            $sections = $this->buildSections->buildFromCollection(
                $page->sections()->where('locale', $locale)->where('enabled', true)->orderBy('position')->orderBy('id')->get(),
                $locale,
                $viewer,
            );
            $formKey = null;
            foreach ($sections as $section) {
                if ($section->content instanceof ContactContentData) {
                    $formKey = $section->content->formKey;
                    break;
                }
            }
            $contactForm = new ContactFormData(
                token: StoreContactMessageRequest::issueFormToken(),
                form: $this->contactForms->publicForm($formKey, $locale),
            );
        }

        return $this->privateResponse($request, Inertia::render('pages/show', PublicPageData::fromTranslation(
            $translation,
            $this->richText->toHtml($translation->body),
            [],
            $preview,
            $layoutMode,
            $sections,
            $contactForm,
        )));
    }

    public function article(Request $request, ArticleTranslation $translation, ContentPreviewData $preview): Response
    {
        $locale = $translation->locale;
        $this->usePublicContext($request, $locale);

        $listUrl = $this->urls->url('articles.index', [], $locale);

        return $this->privateResponse($request, Inertia::render('articles/show', PublicArticleData::fromTranslation(
            $translation,
            $this->richText->toHtml($translation->body),
            $listUrl,
            [],
            $preview,
            PublicTagData::listOf($translation->article->tags, $locale, $this->config->getPublicFallback(), $listUrl),
        )));
    }

    /**
     * The previewed screen needs the public catalog, menus and HTML language
     * of the content locale, and no language alternates (the translations may
     * not be public). Analytics, marketing and chat scripts stay off: they
     * would send the secret preview address to third parties.
     */
    private function usePublicContext(Request $request, string $locale): void
    {
        $this->localization->setContext('public', $locale, $request);
        app()->setLocale($locale);
        app()->setFallbackLocale($this->config->getPublicFallback());

        Inertia::share('locale', $locale);
        Inertia::share('i18n', [...$this->localization->getPayload($request), 'alternateUrls' => []]);
        Inertia::share('navigation', fn () => $this->navigation->shared($locale));
        Inertia::share('consent', null);
    }

    /**
     * Unpublished content must not be indexed or stored by any cache. The
     * public context switch disables the admin-area X-Robots-Tag of
     * PreventIndexingOfPrivateAreas, so it is set here.
     */
    private function privateResponse(Request $request, InertiaResponse $page): Response
    {
        $response = $page->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
