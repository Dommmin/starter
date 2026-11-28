<?php

namespace App\Services\Content;

use App\Models\Redirect;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\PageRepository;
use App\Services\Localization\LocalizationConfig;
use App\Support\Redirects\RedirectPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tells whether a link inside content points at an address of this site that
 * visitors cannot open. External links are never requested.
 */
class InternalLinkChecker
{
    /** @var array<string, bool> */
    private array $checked = [];

    public function __construct(
        private readonly PageRepository $pages,
        private readonly ArticleRepository $articles,
        private readonly LocalizationConfig $localization,
    ) {}

    public function isBroken(string $href): bool
    {
        $parts = parse_url(trim($href));

        if ($parts === false) {
            return false;
        }

        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['host']) && $parts['host'] !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return false;
        }

        $path = $parts['path'] ?? '';

        if (! str_starts_with($path, '/') || str_starts_with($path, '/storage/')) {
            return false;
        }

        return $this->checked[$path] ??= ! $this->resolves($path);
    }

    private function resolves(string $path): bool
    {
        if (Redirect::query()->where('source_path', RedirectPath::source($path))->where('is_active', true)->exists()) {
            return true;
        }

        $default = $this->localization->getPublicDefault();

        // `/{default}/...` answers with a redirect to the address without it.
        if ($path === "/{$default}" || str_starts_with($path, "/{$default}/")) {
            $path = substr($path, strlen($default) + 1) ?: '/';
        }

        try {
            $route = Route::getRoutes()->match(Request::create($path));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return is_file(public_path(ltrim($path, '/')));
        }

        $name = preg_replace('/^localized\./', '', (string) $route->getName());
        $localeParameter = $route->parameter('locale');
        $slugParameter = $route->parameter('slug');
        $locale = is_string($localeParameter) ? $localeParameter : $default;
        $slug = is_string($slugParameter) ? $slugParameter : '';

        return match ($name) {
            'pages.show' => $this->pages->findPublishedTranslation($locale, $slug) !== null
                || $this->pages->findPublishedRedirectTarget($locale, $slug) !== null,
            'articles.show' => $this->articles->findPublishedTranslation($locale, $slug) !== null
                || $this->articles->findPublishedRedirectTarget($locale, $slug) !== null,
            default => true,
        };
    }
}
