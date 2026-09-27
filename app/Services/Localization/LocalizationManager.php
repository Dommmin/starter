<?php

namespace App\Services\Localization;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use LogicException;

class LocalizationManager
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $catalogCache = [];

    public function __construct(protected LocalizationConfig $config) {}

    public function getConfig(): LocalizationConfig
    {
        return $this->config;
    }

    /**
     * Determine if the request belongs to the administration/account area.
     * Includes /admin, /settings and account management routes.
     */
    public function determineArea(Request $request): string
    {
        $route = $request->route();
        if ($route) {
            $area = $route->defaults['_area'] ?? null;
            if ($area === 'admin' || $area === 'public') {
                return (string) $area;
            }

            $name = $route->getName();
            if ($name && (
                str_starts_with($name, 'admin.') ||
                str_starts_with($name, 'profile.') ||
                str_starts_with($name, 'security.') ||
                str_starts_with($name, 'appearance.') ||
                str_starts_with($name, 'admin-locale.') ||
                str_starts_with($name, 'two-factor.') ||
                str_starts_with($name, 'passkey.')
            )) {
                return 'admin';
            }

            $prefix = trim((string) $route->getPrefix(), '/');
            if ($prefix === 'admin' || str_starts_with($prefix, 'admin/')) {
                return 'admin';
            }
        }

        $path = trim($request->path(), '/');

        if (
            $path === 'admin' ||
            str_starts_with($path, 'admin/') ||
            $path === 'settings' ||
            str_starts_with($path, 'settings/') ||
            str_starts_with($path, 'user/')
        ) {
            return 'admin';
        }

        return 'public';
    }

    /**
     * Determine if the request is for an authentication flow.
     */
    public function isAuthPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        $firstSegment = $request->segment(1);
        if (is_string($firstSegment) && $this->config->isPublicLocale($firstSegment)) {
            $path = preg_replace('#^'.preg_quote($firstSegment, '#').'(/|$)#', '', $path);
            $path = trim((string) $path, '/');
        }

        return in_array($path, [
            'login',
            'logout',
            'register',
            'forgot-password',
            'reset-password',
            'two-factor-challenge',
            'user/confirm-password',
            'user/confirmed-password-status',
            'email/verify',
            'email/verification-notification',
        ], true)
        || str_starts_with($path, 'reset-password/')
        || str_starts_with($path, 'email/verify/');
    }

    /**
     * Resolve effective locale for the given request and area.
     */
    public function resolveLocale(Request $request, ?string $area = null): string
    {
        $area ??= $this->determineArea($request);

        if ($area === 'admin') {
            return $this->resolveAdminLocale($request);
        }

        return $this->resolvePublicLocale($request);
    }

    /**
     * Admin locale resolution contract:
     * 1. Active profile preference
     * 2. Active session preference
     * 3. 'en' (always fallback)
     */
    public function resolveAdminLocale(Request $request): string
    {
        $user = $request->user();
        if ($user && ! empty($user->admin_locale) && $this->config->isAdminLocale($user->admin_locale)) {
            return $user->admin_locale;
        }

        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('admin_locale');
            if (is_string($sessionLocale) && $this->config->isAdminLocale($sessionLocale)) {
                return $sessionLocale;
            }
        }

        return $this->config->getAdminDefault(); // Always 'en'
    }

    /**
     * Public locale resolution contract:
     * Exclusively derived from URL prefix if present.
     * Unprefixed auth routes preserve fallback to session locale for guests if valid.
     */
    public function resolvePublicLocale(Request $request): string
    {
        $firstSegment = $request->segment(1);

        if (is_string($firstSegment) && $this->config->isPublicLocale($firstSegment) && $firstSegment !== $this->config->getPublicDefault()) {
            return $firstSegment;
        }

        if ($this->isAuthPath($request) && $request->hasSession()) {
            $sessionLocale = $request->session()->get('admin_locale');
            if (is_string($sessionLocale) && $this->config->isPublicLocale($sessionLocale)) {
                return $sessionLocale;
            }
        }

        return $this->config->getPublicDefault();
    }

    public function setContext(string $area, string $locale, ?Request $request = null): void
    {
        $req = $request ?? (app()->bound('request') ? app('request') : null);
        if ($req instanceof Request) {
            $req->attributes->set('localization.area', $area);
            $req->attributes->set('localization.locale', $locale);
        }
    }

    public function getCurrentArea(?Request $request = null): string
    {
        $req = $request ?? (app()->bound('request') ? app('request') : null);
        if ($req instanceof Request && $req->attributes->has('localization.area')) {
            return (string) $req->attributes->get('localization.area');
        }

        return 'public';
    }

    public function getCurrentLocale(?Request $request = null): string
    {
        $req = $request ?? (app()->bound('request') ? app('request') : null);
        if ($req instanceof Request && $req->attributes->has('localization.locale')) {
            return (string) $req->attributes->get('localization.locale');
        }

        $appLocale = app()->getLocale();
        if ($appLocale && $this->config->isRegistered($appLocale)) {
            return $appLocale;
        }

        return $this->config->getPublicDefault();
    }

    /**
     * Catalog groups (lang/{locale}/{group}.php) sent to the browser per UI
     * scope. The mapping is static on purpose: a page never fetches a
     * missing group at runtime, so every React key must resolve within the
     * scope it renders in (enforced by LocalizationCatalogGateTest). The
     * `validation` group is server-only: validation messages reach the UI
     * already translated in the error bag.
     *
     * - public: landing, CMS pages and public error pages,
     * - auth: login, password reset, e-mail verification, 2FA challenge,
     * - admin: panel and account settings; passkey management and the
     *   password confirmation dialog reuse `auth.passkey`/`auth.confirmPassword`.
     *
     * @var array<string, list<string>>
     */
    public const array CATALOG_SCOPES = [
        'public' => ['common', 'public', 'errors'],
        'auth' => ['common', 'auth', 'errors'],
        'admin' => ['common', 'admin', 'settings', 'auth', 'errors'],
    ];

    /**
     * Groups whose keys the UI reads without the group prefix, such as
     * `brand.name` (common) or `landing.heroTitle` (public). They are sent
     * once, unprefixed; the other groups keep their prefix (`admin.*`).
     *
     * @var list<string>
     */
    public const array UNPREFIXED_GROUPS = ['common', 'public'];

    /**
     * UI scope of the request: the admin area, an authentication flow or
     * the public website.
     */
    public function catalogScope(Request $request, ?string $area = null): string
    {
        $area ??= $this->determineArea($request);

        if ($area === 'admin') {
            return 'admin';
        }

        return $this->isAuthPath($request) ? 'auth' : 'public';
    }

    /**
     * Load the message catalog of a UI scope and locale with fallback.
     *
     * @return array<string, mixed>
     */
    public function getMessagesForScope(string $scope, string $locale): array
    {
        $groups = self::CATALOG_SCOPES[$scope]
            ?? throw new InvalidArgumentException("Unknown localization catalog scope [{$scope}].");

        $fallback = $scope === 'admin'
            ? $this->config->getAdminFallback()
            : $this->config->getPublicFallback();

        $catalog = $this->loadGroupsForLocale($groups, $fallback);

        if ($locale !== $fallback) {
            $catalog = array_replace_recursive($catalog, $this->loadGroupsForLocale($groups, $locale));
        }

        return $this->flattenUnprefixedGroups($catalog);
    }

    /**
     * Merge the unprefixed groups into the payload root and keep the other
     * groups under their own key, so no translation is sent twice.
     *
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    protected function flattenUnprefixedGroups(array $catalog): array
    {
        $messages = [];

        foreach ($catalog as $group => $entries) {
            if (in_array($group, self::UNPREFIXED_GROUPS, true)) {
                $messages = array_replace_recursive($messages, is_array($entries) ? $entries : []);
            }
        }

        foreach ($catalog as $group => $entries) {
            if (in_array($group, self::UNPREFIXED_GROUPS, true)) {
                continue;
            }

            if (array_key_exists($group, $messages)) {
                throw new LogicException("Unprefixed translation key [{$group}] collides with the catalog group of the same name.");
            }

            $messages[$group] = $entries;
        }

        return $messages;
    }

    /**
     * Load raw PHP translations from lang/{locale}/{group}.php.
     *
     * @param  list<string>  $groups
     * @return array<string, mixed>
     */
    protected function loadGroupsForLocale(array $groups, string $locale): array
    {
        $cacheKey = "{$locale}_".implode('_', $groups);
        if (isset($this->catalogCache[$cacheKey])) {
            return $this->catalogCache[$cacheKey];
        }

        $catalog = [];
        $basePath = lang_path($locale);

        foreach ($groups as $group) {
            $file = "{$basePath}/{$group}.php";
            if (File::exists($file)) {
                /** @var array<string, mixed> $data */
                $data = require $file;
                $catalog[$group] = $data;
            } else {
                $catalog[$group] = [];
            }
        }

        $this->catalogCache[$cacheKey] = $catalog;

        return $catalog;
    }

    /**
     * Build the i18n payload for Inertia.
     *
     * @return array{
     *     area: string,
     *     locale: string,
     *     defaultLocale: string,
     *     fallback: string,
     *     dir: string,
     *     availableLocales: list<array{code: string, name: string, native: string, dir: string}>,
     *     messages: array<string, mixed>
     * }
     */
    public function getPayload(Request $request): array
    {
        $area = $request->attributes->get('localization.area') ?? $this->determineArea($request);
        $locale = $request->attributes->get('localization.locale') ?? $this->resolveLocale($request, $area);
        $defaultLocale = $area === 'admin'
            ? $this->config->getAdminDefault()
            : $this->config->getPublicDefault();
        $fallback = $area === 'admin'
            ? $this->config->getAdminFallback()
            : $this->config->getPublicFallback();

        $meta = $this->config->getLocaleMetadata($locale);
        $dir = $meta['dir'] ?? 'ltr';

        return [
            'area' => $area,
            'locale' => $locale,
            'defaultLocale' => $defaultLocale,
            'fallback' => $fallback,
            'dir' => $dir,
            'availableLocales' => $this->config->getAvailableLocalesForArea($area),
            'messages' => $this->getMessagesForScope($this->catalogScope($request, $area), $locale),
        ];
    }
}
