<?php

namespace App\Services\Localization;

use Illuminate\Support\Facades\Route;

class LocalizedUrlGenerator
{
    public function __construct(
        protected LocalizationConfig $config,
        protected LocalizationManager $manager,
    ) {}

    /**
     * Generate canonical public URL for a given route identity and target locale.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function url(string $routeName, array $parameters = [], ?string $targetLocale = null): string
    {
        $targetLocale ??= $this->manager->getCurrentLocale();
        $defaultLocale = $this->config->getPublicDefault();

        if (! $this->config->isPublicLocale($targetLocale)) {
            $targetLocale = $defaultLocale;
        }

        $baseRouteName = str_starts_with($routeName, 'localized.') ? substr($routeName, 10) : $routeName;

        if ($baseRouteName === 'home') {
            if ($targetLocale === $defaultLocale) {
                return url('/');
            }

            return url('/'.$targetLocale);
        }

        // Generic pattern for other public routes if they have localized equivalents
        if ($targetLocale === $defaultLocale) {
            unset($parameters['locale']);

            return route($baseRouteName, $parameters);
        }

        $localizedRouteName = 'localized.'.$baseRouteName;
        if (Route::has($localizedRouteName)) {
            return route($localizedRouteName, array_merge(['locale' => $targetLocale], $parameters));
        }

        return route($baseRouteName, $parameters);
    }

    /**
     * Get all active alternate URLs for SEO and language switcher.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, string>
     */
    public function getAlternateUrls(string $routeName = 'home', array $parameters = []): array
    {
        $alternates = [];
        $publicLocales = $this->config->getPublicLocales();
        $defaultLocale = $this->config->getPublicDefault();
        $baseRouteName = str_starts_with($routeName, 'localized.') ? substr($routeName, 10) : $routeName;

        foreach ($publicLocales as $locale) {
            $alternates[$locale] = $this->url($baseRouteName, $parameters, $locale);
        }

        if (isset($alternates[$defaultLocale])) {
            $alternates['x-default'] = $alternates[$defaultLocale];
        }

        return $alternates;
    }
}
