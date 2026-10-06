<?php

namespace App\Services\Home;

use App\Data\Home\HomeActionData;
use App\Data\Home\HomeLinkData;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionAnchor;
use App\Models\PageTranslation;
use App\Models\User;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Support\Facades\Route;

/**
 * Single source of the closed home page link targets: which targets exist
 * in this installation (editor options and validation) and their URL in a
 * locale (public page). Shared by the editor, its request and the public
 * page so the three never disagree.
 */
class HomeLinkResolver
{
    public function __construct(
        private readonly LocalizedUrlGenerator $urls,
    ) {}

    /**
     * Targets available now; `register` only while registration is enabled
     * (its route exists).
     *
     * @return list<HomeLinkTarget>
     */
    public function availableTargets(): array
    {
        return array_values(array_filter(
            HomeLinkTarget::cases(),
            fn (HomeLinkTarget $target): bool => match ($target) {
                HomeLinkTarget::Login => Route::has('login'),
                HomeLinkTarget::Register => Route::has('register'),
                HomeLinkTarget::Articles => Route::has('articles.index'),
                HomeLinkTarget::Contact, HomeLinkTarget::Page => true,
            },
        ));
    }

    /**
     * Resolve a stored action to a link in the locale, or null when the
     * target is unavailable (disabled route, page not published in it).
     *
     * Guest-only targets depend on the viewer: a signed-in user sees no
     * "Register" action, and "Log in" becomes the administration panel link
     * for a user who may open it or disappears for anyone else.
     *
     * @param  array<int, PageTranslation>  $publishedPages  Published translations of the locale keyed by page id.
     */
    public function resolve(?HomeActionData $action, string $locale, array $publishedPages, ?User $viewer = null): ?HomeLinkData
    {
        if ($action === null || ! in_array($action->target, $this->availableTargets(), true)) {
            return null;
        }

        if ($viewer !== null && in_array($action->target, [HomeLinkTarget::Login, HomeLinkTarget::Register], true)) {
            return $action->target === HomeLinkTarget::Login && $viewer->canAccessAdminPanel()
                ? new HomeLinkData(label: __('common.nav.openAdmin', [], $locale), url: route('admin.index'))
                : null;
        }

        $url = match ($action->target) {
            HomeLinkTarget::Contact => '#'.HomeSectionAnchor::Contact->value,
            HomeLinkTarget::Articles => $this->urls->url('articles.index', [], $locale),
            HomeLinkTarget::Login => $this->urls->url('login', [], $locale),
            HomeLinkTarget::Register => $this->urls->url('register', [], $locale),
            HomeLinkTarget::Page => isset($publishedPages[$action->pageId ?? 0])
                ? $this->urls->url('pages.show', ['slug' => $publishedPages[$action->pageId ?? 0]->slug], $locale)
                : null,
        };

        return $url === null ? null : new HomeLinkData(label: $action->label, url: $url);
    }
}
