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

        [$routes, $routeConflicts] = $this->planRoutes($resource);
        $conflicts = [...$conflicts, ...$routeConflicts];
        if ($routes !== null) {
            $changes[] = $routes;
        }

        foreach (self::LOCALES as $locale) {
            [$catalog, $catalogConflicts] = $this->planCatalog($resource, $locale);
            $conflicts = [...$conflicts, ...$catalogConflicts];
            if ($catalog !== null) {
                $changes[] = $catalog;
            }
        }

        return new GenerationPlan($changes, $conflicts);
    }

    /**
     * Problems with the models referenced by belongsTo fields. The model
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

        foreach ($resource->fieldsOfType('belongsTo') as $field) {
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
     * @return array{0: PlannedChange|null, 1: list<string>}
     */
    private function planCatalog(ResourceBlueprint $resource, string $locale): array
    {
        $relative = "lang/{$locale}/admin.php";
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

        $anchor = strrpos($original, "\n];");
        if ($anchor === false || ! in_array(substr(rtrim(substr($original, 0, $anchor)), -1), [',', '['], true)) {
            return [null, [...$conflicts, "{$relative}: the closing `];` after a trailing comma was not found."]];
        }

        $contents = substr($original, 0, $anchor)
            .PHP_EOL.PHP_EOL.trim($this->renderer->langBlock($resource, $locale), "\n")
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
