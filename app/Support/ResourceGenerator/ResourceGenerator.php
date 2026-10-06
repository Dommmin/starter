<?php

namespace App\Support\ResourceGenerator;

use Illuminate\Filesystem\Filesystem;
use ParseError;
use RuntimeException;
use Throwable;

/**
 * Plans and writes the files of a generated admin resource.
 *
 * Safety contract: nothing is ever overwritten. `plan()` inspects every
 * target first; `write()` refuses a plan with conflicts and writes all files
 * or none (partial writes are rolled back). Existing files are only extended
 * at well-defined anchors (the admin route group, the admin catalogs), after
 * checking that the entry does not exist yet and that the result is valid PHP.
 */
final class ResourceGenerator
{
    public const array LOCALES = ['en', 'pl', 'de'];

    private const string NAVIGATION_FILE = 'resources/js/layouts/admin-layout.tsx';

    private const string NAVIGATION_MARKER = '// app:make-resource: new navigation items';

    private const string PUBLIC_ROUTES_FILE = 'routes/front.php';

    private const string PUBLIC_ROUTES_MARKER = '// app:make-resource: public routes';

    private const string SITEMAP_PROVIDER_FILE = 'app/Providers/SitemapServiceProvider.php';

    private const string SITEMAP_MARKER = '// app:make-resource: sitemap sources';

    private readonly string $basePath;

    private readonly ResourceRenderer $renderer;

    public function __construct(
        private readonly Filesystem $files,
        ?string $basePath = null,
        ?string $stubPath = null,
    ) {
        $this->basePath = rtrim($basePath ?? base_path(), DIRECTORY_SEPARATOR);
        $this->renderer = new ResourceRenderer($files, $stubPath ?? base_path('stubs/resource'));
    }

    public function plan(ResourceBlueprint $resource, string $migrationTimestamp): GenerationPlan
    {
        $changes = [];
        $conflicts = [];

        foreach ($this->renderer->newFiles($resource, $migrationTimestamp) as $path => $contents) {
            if ($this->files->exists($this->path($path))) {
                $conflicts[] = "{$path} already exists.";
            }

            $changes[] = new PlannedChange($path, $contents, null);
        }

        foreach ($this->files->glob($this->path("database/migrations/*_create_{$resource->table()}_table.php")) as $migration) {
            $conflicts[] = 'A migration creating the ['.$resource->table().'] table already exists: '.basename($migration).'.';
        }

        foreach ($resource->manyRelations as $relation) {
            $pivot = $relation->pivotTable($resource->model);
            foreach ($this->files->glob($this->path("database/migrations/*_create_{$pivot}_table.php")) as $migration) {
                $conflicts[] = "A migration creating the [{$pivot}] pivot table already exists: ".basename($migration).'.';
            }
        }

        [$routes, $routeConflicts] = $this->planRoutes($resource);
        $conflicts = [...$conflicts, ...$routeConflicts];
        if ($routes !== null) {
            $changes[] = $routes;
        }

        [$navigation, $navigationConflicts] = $this->planNavigation($resource);
        $conflicts = [...$conflicts, ...$navigationConflicts];
        if ($navigation !== null) {
            $changes[] = $navigation;
        }

        foreach (self::LOCALES as $locale) {
            [$catalog, $catalogConflicts] = $this->planCatalog($resource, $locale);
            $conflicts = [...$conflicts, ...$catalogConflicts];
            if ($catalog !== null) {
                $changes[] = $catalog;
            }
        }

        if ($resource->public) {
            foreach ([
                $this->planPublicRoutes($resource),
                $this->planSitemapSource($resource),
                ...array_map(fn (string $locale): array => $this->planCatalog($resource, $locale, 'public'), self::LOCALES),
            ] as [$change, $changeConflicts]) {
                $conflicts = [...$conflicts, ...$changeConflicts];
                if ($change !== null) {
                    $changes[] = $change;
                }
            }
        }

        return new GenerationPlan($changes, $conflicts);
    }

    /**
     * Problems with the models referenced by belongsTo and belongsToMany fields. The model
     * source is read instead of the database schema, so the check behaves the
     * same in a dry run, in tests and before any migration ran: the label
     * column must be a fillable or documented (`@property`, not `@property-read`) attribute and the
     * model must use HasFactory (the generated factory calls `::factory()`).
     *
     * @return list<string>
     */
    public function relationErrors(ResourceBlueprint $resource): array
    {
        $errors = [];

        foreach ($resource->optionFields() as $field) {
            $model = (string) $field->relatedModel;
            $label = (string) $field->relatedLabel;
            $relative = "app/Models/{$model}.php";

            if ($model === $resource->model || ! $this->files->exists($this->path($relative))) {
                $errors[] = "Field [{$field->name}]: model {$relative} does not exist.";

                continue;
            }

            $source = $this->files->get($this->path($relative));

            if (! in_array($label, self::modelAttributes($source), true)) {
                $errors[] = "Field [{$field->name}]: column [{$label}] is not a fillable or documented attribute of App\\Models\\{$model}.";
            }

            if (! str_contains($source, 'HasFactory')) {
                $errors[] = "Field [{$field->name}]: App\\Models\\{$model} must use HasFactory (the generated factory calls {$model}::factory()).";
            }

            if (preg_match('/\$table\s*=|#\[Table\(/', $source) === 1) {
                $errors[] = "Field [{$field->name}]: App\\Models\\{$model} uses a custom table name, which the generated foreign key does not support.";
            }
        }

        return $errors;
    }

    /**
     * Attribute names declared in a model source: `#[Fillable([...])]`,
     * `$fillable = [...]` and `@property` docblock lines.
     *
     * @return list<string>
     */
    private static function modelAttributes(string $source): array
    {
        $attributes = [];

        if (preg_match('/#\[Fillable\(\[(.*?)\]\)\]/s', $source, $fillable) === 1
            || preg_match('/\$fillable\s*=\s*\[(.*?)\]/s', $source, $fillable) === 1) {
            preg_match_all("/'([a-z0-9_]+)'/", $fillable[1], $names);
            $attributes = $names[1];
        }

        preg_match_all('/@property\s+\S+\s+\$([a-z0-9_]+)/', $source, $documented);

        return array_values(array_unique([...$attributes, ...$documented[1]]));
    }

    /**
     * Write every planned change, or nothing.
     *
     * @throws RuntimeException When the plan has conflicts or a write fails (after rollback).
     */
    public function write(GenerationPlan $plan): void
    {
        if ($plan->conflicts !== []) {
            throw new RuntimeException('Refusing to write: '.implode(' ', $plan->conflicts));
        }

        /** @var list<string> $createdFiles */
        $createdFiles = [];
        /** @var list<string> $createdDirectories */
        $createdDirectories = [];
        /** @var array<string, string> $originals */
        $originals = [];

        try {
            foreach ($plan->changes as $change) {
                $target = $this->path($change->path);

                if ($change->original === null) {
                    if ($this->files->exists($target)) {
                        throw new RuntimeException("{$change->path} appeared while writing.");
                    }

                    $createdDirectories = [...$createdDirectories, ...$this->ensureDirectory(dirname($target))];
                    $this->put($target, $change->contents);
                    $createdFiles[] = $target;

                    continue;
                }

                if ($this->files->get($target) !== $change->original) {
                    throw new RuntimeException("{$change->path} changed while writing.");
                }

                $originals[$target] = $change->original;
                $this->put($target, $change->contents);
            }
        } catch (Throwable $exception) {
            foreach ($createdFiles as $file) {
                $this->files->delete($file);
            }

            foreach (array_reverse($createdDirectories) as $directory) {
                if ($this->files->isDirectory($directory) && $this->files->isEmptyDirectory($directory)) {
                    $this->files->deleteDirectory($directory);
                }
            }

            foreach ($originals as $file => $contents) {
                $this->files->put($file, $contents);
            }

            throw new RuntimeException('Generation failed and was rolled back: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Absolute path of a project-relative path.
     */
    public function path(string $relative): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.$relative;
    }

    private function put(string $target, string $contents): void
    {
        if ($this->files->put($target, $contents) === false) {
            throw new RuntimeException("Could not write {$target}.");
        }
    }

    /**
     * Create missing directories and return the ones created (outermost first).
     *
     * @return list<string>
     */
    private function ensureDirectory(string $directory): array
    {
        $missing = [];
        $current = $directory;

        while (! $this->files->isDirectory($current) && $current !== dirname($current)) {
            array_unshift($missing, $current);
            $current = dirname($current);
        }

        if ($missing !== []) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        return $missing;
    }

    /**
     * @return array{0: PlannedChange|null, 1: list<string>}
     */
    private function planRoutes(ResourceBlueprint $resource): array
    {
        $relative = 'routes/admin.php';
        $target = $this->path($relative);

        if (! $this->files->exists($target)) {
            return [null, ["{$relative} does not exist."]];
        }

        $original = $this->files->get($target);
        $conflicts = [];
        $kebab = $resource->kebabPlural();

        if (str_contains($original, "->name('{$kebab}.") || str_contains($original, "'/{$kebab}'")) {
            $conflicts[] = "{$relative} already defines {$kebab} routes.";
        }

        $controller = "App\\Http\\Controllers\\Admin\\{$resource->plural()}\\{$resource->model}Controller";
        $model = "App\\Models\\{$resource->model}";

        foreach ([$controller, $model] as $class) {
            $short = class_basename($class);
            if (preg_match('/^use (?!'.preg_quote($class, '/').';)[^;]*\\\\'.preg_quote($short, '/').';$/m', $original) === 1) {
                $conflicts[] = "{$relative} already imports another class named {$short}.";
            }
        }

        $anchor = strrpos($original, "\n    });");
        if ($anchor === false) {
            return [null, [...$conflicts, "{$relative}: the admin route group closing `    });` was not found."]];
        }

        $contents = substr($original, 0, $anchor)
            .PHP_EOL.PHP_EOL.trim($this->renderer->routesBlock($resource), "\n")
            .substr($original, $anchor);
        $contents = ImportSorter::sort($contents, [$controller, $model]);

        if (! $this->isValidPhp($contents)) {
            $conflicts[] = "{$relative} would not be valid PHP after the change.";
        }

        return [new PlannedChange($relative, $contents, $original), $conflicts];
    }

    /**
     * Adds the sidebar entry of the resource to the panel layout, before the
     * marker comment inside the content group.
     *
     * @return array{0: PlannedChange|null, 1: list<string>}
     */
    private function planNavigation(ResourceBlueprint $resource): array
    {
        $relative = self::NAVIGATION_FILE;
        $target = $this->path($relative);

        if (! $this->files->exists($target)) {
            return [null, ["{$relative} does not exist."]];
        }

        $original = $this->files->get($target);
        $kebab = $resource->kebabPlural();
        $camel = $resource->camelPlural();

        if (str_contains($original, "@/routes/admin/{$kebab}'")) {
            return [null, ["{$relative} already links to the {$kebab} routes."]];
        }

        $markerPosition = strpos($original, self::NAVIGATION_MARKER);
        $routeImportAnchor = strrpos($original, "from '@/routes/admin");
        $iconImportAnchor = strpos($original, "} from 'lucide-react';");

        if ($markerPosition === false || $routeImportAnchor === false || $iconImportAnchor === false) {
            return [null, ["{$relative}: the navigation marker `".self::NAVIGATION_MARKER.'` or its imports were not found.']];
        }

        $markerLineStart = strrpos(substr($original, 0, $markerPosition), "\n") + 1;
        $indent = str_repeat(' ', 16);
        $entry = implode("\n", [
            "{$indent}section(",
            "{$indent}    '{$kebab}',",
            "{$indent}    t('admin.{$camel}.navLabel'),",
            "{$indent}    {$camel}Index(),",
            "{$indent}    Boxes,",
            "{$indent}),",
        ])."\n";
        $routeImport = "import { index as {$camel}Index } from '@/routes/admin/{$kebab}';\n";
        $routeImportLineEnd = strpos($original, "\n", $routeImportAnchor) + 1;

        $contents = substr($original, 0, $iconImportAnchor)
            .(preg_match('/\bBoxes,/', $original) === 1 ? '' : "    Boxes,\n")
            .substr($original, $iconImportAnchor, $routeImportLineEnd - $iconImportAnchor)
            .$routeImport
            .substr($original, $routeImportLineEnd, $markerLineStart - $routeImportLineEnd)
            .$entry
            .substr($original, $markerLineStart);

        return [new PlannedChange($relative, $contents, $original), []];
    }

    /**
     * Public list and detail routes of a `--public` resource, inserted before
     * the marker of routes/front.php (loaded for every public locale).
     *
     * @return array{0: PlannedChange|null, 1: list<string>}
     */
    private function planPublicRoutes(ResourceBlueprint $resource): array
    {
        $relative = self::PUBLIC_ROUTES_FILE;
        $target = $this->path($relative);

        if (! $this->files->exists($target)) {
            return [null, ["{$relative} does not exist."]];
        }

        $original = $this->files->get($target);
        $kebab = $resource->kebabPlural();
        $conflicts = [];

        if (str_contains($original, "->name('{$kebab}.") || str_contains($original, "'/{$kebab}'")) {
            $conflicts[] = "{$relative} already defines {$kebab} routes.";
        }

        $marker = strpos($original, self::PUBLIC_ROUTES_MARKER);
        if ($marker === false) {
            return [null, [...$conflicts, "{$relative}: the marker `".self::PUBLIC_ROUTES_MARKER.'` was not found.']];
        }

        $controller = "App\\Http\\Controllers\\Content\\Public{$resource->model}Controller";
        $contents = substr($original, 0, $marker)
            .trim($this->renderer->publicRoutesBlock($resource), "\n").PHP_EOL.PHP_EOL
            .substr($original, $marker);
        $contents = ImportSorter::sort($contents, [$controller]);

        if (! $this->isValidPhp($contents)) {
            $conflicts[] = "{$relative} would not be valid PHP after the change.";
        }

        return [new PlannedChange($relative, $contents, $original), $conflicts];
    }

    /**
     * Registers the sitemap source of a `--public` resource before the marker
     * of the sitemap service provider.
     *
     * @return array{0: PlannedChange|null, 1: list<string>}
     */
    private function planSitemapSource(ResourceBlueprint $resource): array
    {
        $relative = self::SITEMAP_PROVIDER_FILE;
        $target = $this->path($relative);

        if (! $this->files->exists($target)) {
            return [null, ["{$relative} does not exist."]];
        }

        $original = $this->files->get($target);
        $class = "{$resource->plural()}SitemapSource";
        $marker = strpos($original, self::SITEMAP_MARKER);

        if ($marker === false) {
            return [null, ["{$relative}: the marker `".self::SITEMAP_MARKER.'` was not found.']];
        }

        if (str_contains($original, "{$class}::class")) {
            return [null, ["{$relative} already registers {$class}."]];
        }

        $lineStart = strrpos(substr($original, 0, $marker), "\n") + 1;
        $contents = substr($original, 0, $lineStart)
            .str_repeat(' ', 12)."{$class}::class,\n"
            .substr($original, $lineStart);
        $contents = ImportSorter::sort($contents, ["App\\Actions\\Seo\\{$class}"]);

        return [new PlannedChange($relative, $contents, $original), $this->isValidPhp($contents) ? [] : ["{$relative} would not be valid PHP after the change."]];
    }

    /**
     * @param  'admin'|'public'  $group  Catalog file; the public one gets the public page texts.
     * @return array{0: PlannedChange|null, 1: list<string>}
     */
    private function planCatalog(ResourceBlueprint $resource, string $locale, string $group = 'admin'): array
    {
        $relative = "lang/{$locale}/{$group}.php";
        $target = $this->path($relative);

        if (! $this->files->exists($target)) {
            return [null, ["{$relative} does not exist."]];
        }

        $original = $this->files->get($target);
        $catalog = (static fn (string $path): mixed => require $path)($target);
        $key = $resource->camelPlural();

        if (! is_array($catalog)) {
            return [null, ["{$relative} does not return an array."]];
        }

        $conflicts = [];
        if (array_key_exists($key, $catalog)) {
            $conflicts[] = "{$relative} already contains the [{$key}] key.";
        }

        // Public and common keys are read without a group prefix, so a public
        // module must not shadow a common key either.
        $common = $this->path("lang/{$locale}/common.php");
        if ($group === 'public' && $this->files->exists($common)) {
            $commonCatalog = (static fn (string $path): mixed => require $path)($common);
            if (is_array($commonCatalog) && array_key_exists($key, $commonCatalog)) {
                $conflicts[] = "lang/{$locale}/common.php already contains the [{$key}] key.";
            }
        }

        $anchor = strrpos($original, "\n];");
        if ($anchor === false || ! in_array(substr(rtrim(substr($original, 0, $anchor)), -1), [',', '['], true)) {
            return [null, [...$conflicts, "{$relative}: the closing `];` after a trailing comma was not found."]];
        }

        $block = $group === 'public'
            ? $this->renderer->publicLangBlock($resource, $locale)
            : $this->renderer->langBlock($resource, $locale);
        $contents = substr($original, 0, $anchor)
            .PHP_EOL.PHP_EOL.trim($block, "\n")
            .substr($original, $anchor);

        if (! $this->isValidPhp($contents)) {
            $conflicts[] = "{$relative} would not be valid PHP after the change.";
        }

        return [new PlannedChange($relative, $contents, $original), $conflicts];
    }

    private function isValidPhp(string $contents): bool
    {
        try {
            return token_get_all($contents, TOKEN_PARSE) !== [];
        } catch (ParseError) {
            return false;
        }
    }
}
