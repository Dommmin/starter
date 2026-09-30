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

test('public payload does not leak admin catalogs nor duplicate unprefixed groups', function () {
    /** @var LocalizationManager $manager */
    $manager = app(LocalizationManager::class);

    $request = Request::create('/', 'GET');
    $payload = $manager->getPayload($request);

    expect($payload['area'])->toBe('public')
        ->and(array_keys($payload['messages']))->toContain('a11y', 'landing', 'contact', 'errors')
        ->and(array_keys($payload['messages']))->not->toContain('common', 'public', 'validation', 'auth', 'admin', 'settings');
});

test('each request resolves the catalog scope of its area', function (string $path, string $scope) {
    /** @var LocalizationManager $manager */
    $manager = app(LocalizationManager::class);

    expect($manager->catalogScope(Request::create($path, 'GET')))->toBe($scope);
})->with([
    'landing' => ['/', 'public'],
    'localized landing' => ['/pl', 'public'],
    'cms page' => ['/privacy-policy', 'public'],
    'login' => ['/login', 'auth'],
    'localized login' => ['/pl/login', 'auth'],
    'password reset' => ['/reset-password/token', 'auth'],
    'two-factor challenge' => ['/two-factor-challenge', 'auth'],
    'password confirmation' => ['/user/confirm-password', 'admin'],
    'admin panel' => ['/admin/pages', 'admin'],
    'account settings' => ['/settings/profile', 'admin'],
]);

test('catalog scopes send only their mapped groups', function (string $scope, array $prefixedGroups, array $absentGroups) {
    /** @var LocalizationManager $manager */
    $manager = app(LocalizationManager::class);

    $messages = $manager->getMessagesForScope($scope, 'en');

    expect(data_get($messages, 'brand.name'))->toBe('Punkt Startowy')
        ->and(array_keys($messages))->toContain(...$prefixedGroups)
        ->and(array_keys($messages))->not->toContain('common', 'public', 'validation', ...$absentGroups);
})->with([
    'public' => ['public', ['errors', 'landing', 'contact'], ['auth', 'admin', 'settings']],
    'auth' => ['auth', ['errors', 'auth'], ['admin', 'settings', 'landing', 'contact']],
    'admin' => ['admin', ['errors', 'admin', 'settings', 'auth'], ['landing', 'contact']],
]);

test('shared i18n prop of rendered pages follows the scope mapping', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('i18n.messages.auth.login')
            ->missing('i18n.messages.landing')
            ->missing('i18n.messages.admin')
            ->missing('i18n.messages.validation'));

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('i18n.messages.landing')
            ->missing('i18n.messages.auth')
            ->missing('i18n.messages.public')
            ->missing('i18n.messages.common'));
});

test('React translation keys resolve within every catalog scope the module renders in', function () {
    /** @var LocalizationManager $manager */
    $manager = app(LocalizationManager::class);

    $scopeKeys = [];
    foreach (array_keys(LocalizationManager::CATALOG_SCOPES) as $scope) {
        $scopeKeys[$scope] = flattenTranslationKeys($manager->getMessagesForScope($scope, 'en'));
    }

    // The local public showcase (PublicDesignSystemController) renders in the
    // public scope plus the `admin.designSystem` group added to its payload.
    $scopeKeys['design-system'] = [
        ...$scopeKeys['public'],
        ...flattenTranslationKeys(['admin' => ['designSystem' => trans('admin.designSystem', [], 'en')]]),
    ];

    $missing = [];

    foreach (reactModuleScopes() as $file => $scopes) {
        $source = (string) file_get_contents($file);
        $relative = str_replace(resource_path('js').'/', '', $file);

        preg_match_all("/\\bt\\(\\s*['\"]([^'\"]+)['\"]/", $source, $literal);
        preg_match_all('/\\bt\\(\\s*`([^`$]*)\\$\\{/', $source, $templates);

        foreach ($scopes as $scope) {
            foreach ($literal[1] as $key) {
                if (! array_key_exists($key, $scopeKeys[$scope])) {
                    $missing[] = "[{$key}] in {$relative} (scope {$scope})";
                }
            }

            foreach ($templates[1] as $prefix) {
                $hasPrefix = collect(array_keys($scopeKeys[$scope]))
                    ->contains(fn (string $key): bool => str_starts_with($key, $prefix));

                if (! $hasPrefix) {
                    $missing[] = "[{$prefix}*] in {$relative} (scope {$scope})";
                }
            }
        }
    }

    expect($missing)->toBe([], 'Translation keys missing from the catalog scope of the module');
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

/**
 * Catalog scopes each React module can render in, derived from the static
 * import graph (`import ... from`, `export ... from`; dynamic imports and
 * type-only imports are not followed). Roots mirror page-resolver.ts: pages
 * by name, layouts by group, the app shell in every scope and lazily loaded
 * modules explicitly.
 *
 * @return array<string, list<string>>
 */
function reactModuleScopes(): array
{
    $base = resource_path('js');
    $allScopes = array_keys(LocalizationManager::CATALOG_SCOPES);

    $roots = [
        "{$base}/app.tsx" => $allScopes,
        "{$base}/layouts/auth-layout.tsx" => ['auth'],
        "{$base}/layouts/admin-layout.tsx" => ['admin'],
        "{$base}/layouts/settings/layout.tsx" => ['admin'],
        "{$base}/layouts/app-layout.tsx" => ['admin'],
        // Loaded on the first 423 response of a password-confirmed route.
        "{$base}/components/password-confirmation-dialog.tsx" => ['admin'],
    ];

    foreach (File::allFiles("{$base}/pages") as $pageFile) {
        $path = $pageFile->getPathname();

        if (! str_ends_with($path, '.tsx') || str_ends_with($path, '.test.tsx')) {
            continue;
        }

        $name = substr(str_replace("{$base}/pages/", '', $path), 0, -4);

        $roots[$path] = match (true) {
            $name === 'welcome', str_starts_with($name, 'pages/'), str_starts_with($name, 'articles/') => ['public'],
            str_starts_with($name, 'errors/') => $allScopes,
            str_starts_with($name, 'design-system/') => ['design-system'],
            // Unprefixed /user/confirm-password belongs to the admin area.
            $name === 'auth/confirm-password' => ['auth', 'admin'],
            str_starts_with($name, 'auth/') => ['auth'],
            default => ['admin'],
        };
    }

    $scopes = [];
    $queue = [];

    foreach ($roots as $file => $rootScopes) {
        expect(File::exists($file))->toBeTrue("Scope root [{$file}] does not exist");
        $queue[] = [$file, $rootScopes];
    }

    while ($queue !== []) {
        [$file, $fileScopes] = array_shift($queue);
        $known = $scopes[$file] ?? [];
        $added = array_values(array_diff($fileScopes, $known));

        if ($added === []) {
            continue;
        }

        $scopes[$file] = [...$known, ...$added];

        foreach (staticImports($file) as $imported) {
            $queue[] = [$imported, $added];
        }
    }

    return $scopes;
}

/**
 * Resolved local files statically imported by a module. Named imports from
 * an `export *` barrel (design-system primitives) resolve to the modules
 * that declare those names, so importing `Button` does not pull in every
 * primitive of the barrel.
 *
 * @return list<string>
 */
function staticImports(string $file): array
{
    $source = (string) file_get_contents($file);
    preg_match_all(
        '/^\s*(import|export)\s+(?!type\b)(?:([^;]*?)\s+from\s+)?[\'"]([^\'"]+)[\'"]/ms',
        $source,
        $matches,
        PREG_SET_ORDER,
    );

    $resolved = [];

    foreach ($matches as [, $keyword, $clause, $specifier]) {
        $target = resolveModule($file, $specifier);

        if ($target === null) {
            continue;
        }

        $names = $keyword === 'import' ? importedNames($clause) : null;
        $barrel = barrelExports($target);

        if ($names !== null && $barrel !== null) {
            foreach ($names as $name) {
                $resolved = [...$resolved, ...($barrel[$name] ?? [$target])];
            }

            continue;
        }

        $resolved[] = $target;
    }

    return array_values(array_unique($resolved));
}

function resolveModule(string $from, string $specifier): ?string
{
    if (str_starts_with($specifier, '@/')) {
        $candidate = resource_path('js/'.substr($specifier, 2));
    } elseif (str_starts_with($specifier, '.')) {
        $candidate = dirname($from).'/'.$specifier;
    } else {
        return null;
    }

    foreach (['', '.tsx', '.ts', '/index.tsx', '/index.ts'] as $suffix) {
        $path = realpath($candidate.$suffix);

        if ($path !== false && is_file($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Value names of an import clause (`Foo, { A, B as C, type D }`); null for
 * namespace imports, which need the whole module.
 *
 * @return list<string>|null
 */
function importedNames(string $clause): ?array
{
    if ($clause === '' || str_contains($clause, '*')) {
        return null;
    }

    $names = [];

    if (preg_match('/\{([^}]*)\}/', $clause, $braces) === 1) {
        foreach (explode(',', $braces[1]) as $part) {
            $part = trim($part);

            if ($part === '' || str_starts_with($part, 'type ')) {
                continue;
            }

            $names[] = trim(explode(' as ', $part)[0]);
        }
    }

    $default = trim((string) preg_replace('/\{[^}]*\}/', '', $clause), " ,\n\t");

    if ($default !== '') {
        $names[] = 'default';
    }

    return $names;
}

/**
 * For a module consisting of `export * from` lines only: exported name =>
 * declaring modules. Null for any other module.
 *
 * @return array<string, list<string>>|null
 */
function barrelExports(string $file): ?array
{
    static $cache = [];

    if (array_key_exists($file, $cache)) {
        return $cache[$file];
    }

    $source = trim((string) preg_replace('~//[^\n]*|/\*.*?\*/~s', '', (string) file_get_contents($file)));
    $lines = array_filter(array_map('trim', explode("\n", $source)));

    if ($lines === [] || array_filter($lines, fn (string $line): bool => preg_match('/^export \* from [\'"][^\'"]+[\'"];?$/', $line) !== 1) !== []) {
        return $cache[$file] = null;
    }

    $exports = [];

    foreach ($lines as $line) {
        preg_match('/[\'"]([^\'"]+)[\'"]/', $line, $specifier);
        $module = resolveModule($file, $specifier[1]);

        if ($module === null) {
            continue;
        }

        $moduleSource = (string) file_get_contents($module);
        preg_match_all('/export\s+(?:default\s+)?(?:async\s+)?(?:function|const|let|class)\s+(\w+)/', $moduleSource, $declared);
        preg_match_all('/export\s*\{([^}]*)\}/', $moduleSource, $lists);

        $names = $declared[1];
        foreach ($lists[1] as $list) {
            foreach (explode(',', $list) as $part) {
                $part = trim($part);
                if ($part !== '' && ! str_starts_with($part, 'type ')) {
                    $segments = explode(' as ', $part);
                    $names[] = trim(end($segments));
                }
            }
        }

        foreach ($names as $name) {
            $exports[$name][] = $module;
        }
    }

    return $cache[$file] = $exports;
}
