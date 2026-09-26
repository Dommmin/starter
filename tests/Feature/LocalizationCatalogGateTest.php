<?php

use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * @return array<string, array<string, array<string, mixed>>>
 */
function activeCatalogs(): array
{
    /** @var LocalizationConfig $config */
    $config = app(LocalizationConfig::class);
    $locales = array_unique(array_merge($config->getPublicLocales(), $config->getAdminLocales()));
    $groups = ['common', 'public', 'auth', 'settings', 'admin', 'errors', 'validation'];
    $catalogs = [];

    foreach ($locales as $locale) {
        foreach ($groups as $group) {
            $catalogFile = lang_path("{$locale}/{$group}.php");
            expect(File::exists($catalogFile))->toBeTrue(
                "Catalog file [{$group}.php] missing for active locale [{$locale}]",
            );

            /** @var array<string, mixed> $catalog */
            $catalog = require $catalogFile;
            $catalogs[$locale][$group] = $catalog;
        }
    }

    return $catalogs;
}

test('all registered locales have required catalog groups', function () {
    /** @var LocalizationConfig $config */
    $config = app(LocalizationConfig::class);
    $allLocales = array_unique(array_merge($config->getPublicLocales(), $config->getAdminLocales()));

    $requiredGroups = ['common', 'public', 'auth', 'settings', 'admin', 'errors', 'validation'];

    foreach ($allLocales as $locale) {
        $localePath = lang_path($locale);
        expect(File::isDirectory($localePath))->toBeTrue("Locale directory [{$locale}] must exist in lang/");

        foreach ($requiredGroups as $group) {
            $groupFile = "{$localePath}/{$group}.php";
            expect(File::exists($groupFile))->toBeTrue("Catalog file [{$group}.php] missing for locale [{$locale}]");
        }
    }
});

test('translations match keys and parameters with base english catalog', function () {
    $catalogs = activeCatalogs();
    expect(array_key_exists('en', $catalogs))->toBeTrue(
        'English catalog must be active because it is the catalog schema.',
    );

    $groups = array_keys($catalogs['en']);

    foreach ($groups as $group) {
        $enCatalog = $catalogs['en'][$group];
        $translationKeysEn = flattenTranslationKeys($enCatalog);
        $flattenedEn = flattenArray($enCatalog);

        foreach ($catalogs as $locale => $localeCatalogs) {
            if ($locale === 'en') {
                continue;
            }

            $locCatalog = $localeCatalogs[$group];
            $translationKeysLoc = flattenTranslationKeys($locCatalog);
            $flattenedLoc = flattenArray($locCatalog);

            expect(array_keys($translationKeysLoc))->toEqualCanonicalizing(
                array_keys($translationKeysEn),
                "Translation key set mismatch for locale [{$locale}] in group [{$group}]",
            );

            foreach ($flattenedEn as $key => $enValue) {
                expect(array_key_exists($key, $flattenedLoc))->toBeTrue(
                    "Missing translation key [{$group}.{$key}] for locale [{$locale}]"
                );

                if (is_string($enValue) && is_string($flattenedLoc[$key])) {
                    preg_match_all('/(:[a-zA-Z_]+|\{[a-zA-Z_]+\})/', $enValue, $enParams);
                    preg_match_all('/(:[a-zA-Z_]+|\{[a-zA-Z_]+\})/', $flattenedLoc[$key], $locParams);

                    sort($enParams[0]);
                    sort($locParams[0]);

                    expect($locParams[0])->toBe(
                        $enParams[0],
                        "Parameter mismatch in [{$group}.{$key}] for locale [{$locale}]"
                    );
                }
            }
        }
    }
});

test('plural categories match required forms per language', function () {
    /** @var LocalizationConfig $config */
    $config = app(LocalizationConfig::class);
    $activeLocales = array_unique(array_merge($config->getPublicLocales(), $config->getAdminLocales()));
    $requirements = [
        'en' => ['one', 'other'],
        'de' => ['one', 'other'],
        'pl' => ['one', 'few', 'many', 'other'],
    ];

    foreach ($requirements as $locale => $expectedCategories) {
        if (! in_array($locale, $activeLocales, true)) {
            continue;
        }

        $catalog = require lang_path("{$locale}/common.php");
        assertPluralForms($catalog, $locale, $expectedCategories);
    }
});

test('literal React translation keys exist in the English catalog schema', function () {
    $catalogs = activeCatalogs();
    $messages = [];

    foreach ($catalogs['en'] as $group => $catalog) {
        $messages[$group] = $catalog;
    }

    foreach (['common', 'public'] as $group) {
        $catalog = $catalogs['en'][$group];
        $messages = array_replace_recursive($messages, $catalog);
    }

    $keys = flattenArray($messages);

    foreach (File::allFiles(resource_path('js')) as $sourceFile) {
        if (! in_array($sourceFile->getExtension(), ['ts', 'tsx', 'js', 'jsx'], true)) {
            continue;
        }

        preg_match_all("/\\bt\\(\\s*['\"]([^'\"]+)['\"]/", $sourceFile->getContents(), $matches);

        foreach ($matches[1] as $key) {
            expect(array_key_exists($key, $keys))->toBeTrue(
                "React translation key [{$key}] used in [{$sourceFile->getRelativePathname()}] is missing from the English catalog schema",
            );
        }
    }
});

test('literal PHP translation keys exist in the English catalog schema', function () {
    $catalogs = activeCatalogs();
    $keys = [];

    foreach ($catalogs['en'] as $group => $catalog) {
        foreach (flattenArray($catalog) as $key => $value) {
            $keys["{$group}.{$key}"] = $value;
        }
    }

    foreach ([app_path(), base_path('routes')] as $directory) {
        foreach (File::allFiles($directory) as $sourceFile) {
            if ($sourceFile->getExtension() !== 'php') {
                continue;
            }

            preg_match_all("/\\b__(?:\\s*)\\(\\s*['\"]([a-z][a-zA-Z0-9_.-]*)['\"]/", $sourceFile->getContents(), $matches);

            foreach ($matches[1] as $key) {
                expect(array_key_exists($key, $keys))->toBeTrue(
                    "PHP translation key [{$key}] used in [{$sourceFile->getRelativePathname()}] is missing from the English catalog schema",
                );
            }
        }
    }
});

test('public payload does not leak admin catalogs', function () {
    /** @var LocalizationManager $manager */
    $manager = app(LocalizationManager::class);

    $request = Request::create('/', 'GET');
    $payload = $manager->getPayload($request);

    expect($payload['area'])->toBe('public')
        ->and(array_key_exists('common', $payload['messages']))->toBeTrue()
        ->and(array_key_exists('public', $payload['messages']))->toBeTrue()
        ->and(array_key_exists('a11y', $payload['messages']))->toBeTrue()
        ->and(array_key_exists('landing', $payload['messages']))->toBeTrue()
        ->and(array_key_exists('admin', $payload['messages']))->toBeFalse()
        ->and(array_key_exists('settings', $payload['messages']))->toBeFalse();
});

test('admin payload exposes common UI keys without a catalog prefix', function () {
    /** @var LocalizationManager $manager */
    $manager = app(LocalizationManager::class);

    $request = Request::create('/settings/profile', 'GET');
    $payload = $manager->getPayload($request);

    expect($payload['area'])->toBe('admin')
        ->and(data_get($payload['messages'], 'brand.name'))->toBe('Punkt Startowy')
        ->and(data_get($payload['messages'], 'nav.profile'))->toBe('Profile')
        ->and(data_get($payload['messages'], 'a11y.accountSettings'))->toBe('Account settings')
        ->and(data_get($payload['messages'], 'settings.title'))->toBe('Settings');
});

test('html lang and dir attributes are rendered in initial response', function () {
    $response = $this->get('/');
    $response->assertOk();

    // Default public is 'en' and dir 'ltr'
    $response->assertSee('lang="en"', false);
    $response->assertSee('dir="ltr"', false);
});

/**
 * Helper to flatten nested array into dot-notation keys.
 *
 * @param  array<string, mixed>  $array
 * @return array<string, mixed>
 */
function flattenArray(array $array, string $prefix = ''): array
{
    $result = [];
    foreach ($array as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $fullKey));
        } else {
            $result[$fullKey] = $value;
        }
    }

    return $result;
}

/**
 * @param  array<string, mixed>  $array
 * @return array<string, null>
 */
function flattenTranslationKeys(array $array, string $prefix = ''): array
{
    $result = [];
    $pluralCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];

    foreach ($array as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        if (is_array($value)) {
            if (array_intersect(array_keys($value), $pluralCategories) !== []) {
                $result[$fullKey] = null;

                continue;
            }

            $result = array_merge($result, flattenTranslationKeys($value, $fullKey));

            continue;
        }

        $result[$fullKey] = null;
    }

    return $result;
}

/**
 * @param  array<string, mixed>  $catalog
 * @param  list<string>  $requiredCategories
 */
function assertPluralForms(array $catalog, string $locale, array $requiredCategories, string $prefix = ''): void
{
    foreach ($catalog as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (! is_array($value)) {
            continue;
        }

        $pluralCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];
        if (array_intersect(array_keys($value), $pluralCategories) !== []) {
            foreach ($requiredCategories as $category) {
                expect(array_key_exists($category, $value))->toBeTrue(
                    "Missing required plural category [{$category}] for [{$fullKey}] in locale [{$locale}]",
                );
            }
        }

        assertPluralForms($value, $locale, $requiredCategories, $fullKey);
    }
}
