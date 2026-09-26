<?php

namespace App\Services\Localization;

use App\Services\Localization\Exceptions\InvalidLocalizationConfigurationException;

class LocalizationConfig
{
    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(protected ?array $config = null)
    {
        $this->config ??= config('localization', []);
    }

    /**
     * Validate the entire localization configuration.
     *
     * @throws InvalidLocalizationConfigurationException
     */
    public function validate(): void
    {
        $registry = $this->config['registry'] ?? null;
        if (! is_array($registry) || empty($registry)) {
            throw new InvalidLocalizationConfigurationException('Localization registry must be a non-empty array.');
        }

        foreach ($registry as $code => $meta) {
            if (! is_string($code) || $code === '') {
                throw new InvalidLocalizationConfigurationException('Each registry key must be a non-empty string locale code.');
            }
            if (! is_array($meta)) {
                throw new InvalidLocalizationConfigurationException("Metadata for locale [{$code}] must be an array.");
            }
            foreach (['code', 'name', 'native', 'dir'] as $requiredField) {
                if (empty($meta[$requiredField]) || ! is_string($meta[$requiredField])) {
                    throw new InvalidLocalizationConfigurationException("Metadata field [{$requiredField}] is required for locale [{$code}].");
                }
            }
            if (! in_array($meta['dir'], ['ltr', 'rtl'], true)) {
                throw new InvalidLocalizationConfigurationException("Invalid direction [{$meta['dir']}] for locale [{$code}]. Must be 'ltr' or 'rtl'.");
            }
        }

        // Validate public locales
        $publicLocales = $this->config['public_locales'] ?? null;
        if (! is_array($publicLocales) || empty($publicLocales)) {
            throw new InvalidLocalizationConfigurationException('Configuration [public_locales] must be a non-empty array.');
        }
        if (count($publicLocales) !== count(array_unique($publicLocales))) {
            throw new InvalidLocalizationConfigurationException('Configuration [public_locales] cannot contain duplicate locales.');
        }
        foreach ($publicLocales as $loc) {
            if (! isset($registry[$loc])) {
                throw new InvalidLocalizationConfigurationException("Public locale [{$loc}] is not registered in localization registry.");
            }
        }

        $publicDefault = $this->config['public_default'] ?? null;
        if (! is_string($publicDefault) || ! in_array($publicDefault, $publicLocales, true)) {
            throw new InvalidLocalizationConfigurationException('Configuration [public_default] must be one of active public_locales.');
        }

        $publicFallback = $this->config['public_fallback'] ?? null;
        if (! is_string($publicFallback) || ! in_array($publicFallback, $publicLocales, true)) {
            throw new InvalidLocalizationConfigurationException('Configuration [public_fallback] must be one of active public_locales.');
        }

        // Validate admin locales
        $adminLocales = $this->config['admin_locales'] ?? null;
        if (! is_array($adminLocales) || empty($adminLocales)) {
            throw new InvalidLocalizationConfigurationException('Configuration [admin_locales] must be a non-empty array.');
        }
        if (count($adminLocales) !== count(array_unique($adminLocales))) {
            throw new InvalidLocalizationConfigurationException('Configuration [admin_locales] cannot contain duplicate locales.');
        }
        foreach ($adminLocales as $loc) {
            if (! isset($registry[$loc])) {
                throw new InvalidLocalizationConfigurationException("Admin locale [{$loc}] is not registered in localization registry.");
            }
        }

        if (! in_array('en', $adminLocales, true)) {
            throw new InvalidLocalizationConfigurationException("Configuration [admin_locales] must always include 'en'.");
        }

        $adminDefault = $this->config['admin_default'] ?? 'en';
        if ($adminDefault !== 'en') {
            throw new InvalidLocalizationConfigurationException("Configuration [admin_default] must always be 'en'.");
        }

        $adminFallback = $this->config['admin_fallback'] ?? 'en';
        if ($adminFallback !== 'en') {
            throw new InvalidLocalizationConfigurationException("Configuration [admin_fallback] must always be 'en'.");
        }
    }

    /**
     * @return array<string, array{code: string, name: string, native: string, dir: string}>
     */
    public function getRegistry(): array
    {
        return $this->config['registry'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getPublicLocales(): array
    {
        return $this->config['public_locales'] ?? ['en'];
    }

    public function getPublicDefault(): string
    {
        return (string) ($this->config['public_default'] ?? 'en');
    }

    public function getPublicFallback(): string
    {
        return (string) ($this->config['public_fallback'] ?? 'en');
    }

    /**
     * @return list<string>
     */
    public function getAdminLocales(): array
    {
        return $this->config['admin_locales'] ?? ['en'];
    }

    public function getAdminDefault(): string
    {
        return 'en';
    }

    public function getAdminFallback(): string
    {
        return 'en';
    }

    public function isRegistered(string $locale): bool
    {
        return isset($this->getRegistry()[$locale]);
    }

    public function isPublicLocale(string $locale): bool
    {
        return in_array($locale, $this->getPublicLocales(), true);
    }

    public function isAdminLocale(string $locale): bool
    {
        return in_array($locale, $this->getAdminLocales(), true);
    }

    /**
     * @return array{code: string, name: string, native: string, dir: string}|null
     */
    public function getLocaleMetadata(string $locale): ?array
    {
        return $this->getRegistry()[$locale] ?? null;
    }

    public function getDirection(string $locale): string
    {
        return $this->getLocaleMetadata($locale)['dir'] ?? 'ltr';
    }

    /**
     * Get list of locale objects for public or admin area.
     *
     * @return list<array{code: string, name: string, native: string, dir: string}>
     */
    public function getAvailableLocalesForArea(string $area): array
    {
        $codes = $area === 'admin' ? $this->getAdminLocales() : $this->getPublicLocales();
        $registry = $this->getRegistry();

        $result = [];
        foreach ($codes as $code) {
            if (isset($registry[$code])) {
                $result[] = $registry[$code];
            }
        }

        return $result;
    }
}
