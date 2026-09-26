<?php

namespace App\Support\ResourceGenerator;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Validated definition of a generated admin resource and every name derived
 * from it (table, routes, i18n keys, TypeScript namespaces).
 */
final readonly class ResourceBlueprint
{
    /**
     * Model names that already exist in the starter or would shadow framework
     * and language concepts. Compared case-insensitively.
     */
    private const array RESERVED_NAMES = [
        'admin', 'controller', 'dashboard', 'data', 'enum', 'error', 'listing', 'locale', 'media',
        'mediaasset', 'model', 'page', 'pagetranslation', 'policy', 'request', 'resource', 'role',
        'seo', 'setting', 'settings', 'sitemap', 'user', 'content',
        // PHP and JavaScript keywords that become class, variable or import names.
        'abstract', 'and', 'array', 'as', 'async', 'await', 'bool', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'debugger', 'declare', 'default', 'delete', 'do', 'echo',
        'else', 'empty', 'eval', 'exit', 'export', 'extends', 'false', 'final', 'finally', 'float', 'fn',
        'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'import', 'in', 'include',
        'instanceof', 'int', 'interface', 'isset', 'iterable', 'let', 'list', 'match', 'mixed', 'namespace',
        'never', 'new', 'null', 'object', 'of', 'or', 'parent', 'print', 'private', 'protected', 'public',
        'readonly', 'require', 'return', 'self', 'static', 'string', 'super', 'switch', 'this', 'throw',
        'trait', 'true', 'try', 'typeof', 'unset', 'use', 'var', 'void', 'while', 'with', 'xor', 'yield',
    ];

    /**
     * Columns managed by the generator itself, keys of the list query string
     * and form payload, and Eloquent model internals.
     */
    private const array RESERVED_FIELDS = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'search', 'sort', 'direction', 'page', 'conflict', 'can', 'items', 'filters', 'pagination',
        'attributes', 'appends', 'casts', 'changes', 'connection', 'exists', 'fillable', 'guarded',
        'hidden', 'incrementing', 'key', 'original', 'relations', 'table', 'timestamps', 'touches', 'visible',
    ];

    private const array SYSTEM_SORT_COLUMNS = ['id', 'created_at', 'updated_at'];

    /**
     * @param  list<ResourceField>  $fields
     * @param  list<string>  $searchable
     * @param  list<string>  $sortable
     * @param  list<string>  $filters
     */
    private function __construct(
        public string $model,
        public array $fields,
        public array $searchable,
        public array $sortable,
        public array $filters,
    ) {}

    /**
     * Parse and validate the command input.
     *
     * @throws InvalidArgumentException With every problem found, one per line.
     */
    public static function parse(string $name, string $fields, string $searchable = '', string $sortable = '', string $filters = ''): self
    {
        $errors = self::validateName($name);

        [$parsedFields, $fieldErrors] = self::parseFields($fields);
        $errors = [...$errors, ...$fieldErrors];

        $byName = [];
        foreach ($parsedFields as $field) {
            $byName[$field->name] = $field;
        }

        $searchableList = self::splitList($searchable);
        foreach ($searchableList as $column) {
            $field = $byName[$column] ?? null;
            if ($field === null || ! in_array($field->type, ['string', 'text'], true)) {
                $errors[] = "Searchable column [{$column}] must be a string or text field.";
            }
        }

        $sortableList = self::splitList($sortable);
        foreach ($sortableList as $column) {
            $field = $byName[$column] ?? null;
            $isSystem = in_array($column, self::SYSTEM_SORT_COLUMNS, true);
            if (! $isSystem && ($field === null || ! $field->isListed())) {
                $errors[] = "Sortable column [{$column}] must be a non-text field or one of: ".implode(', ', self::SYSTEM_SORT_COLUMNS).'.';
            }
        }

        $filterList = self::splitList($filters);
        foreach ($filterList as $column) {
            $field = $byName[$column] ?? null;
            if ($field === null || ! in_array($field->type, ['boolean', 'enum'], true)) {
                $errors[] = "Filter [{$column}] must be a boolean or enum field.";
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(PHP_EOL, $errors));
        }

        return new self(
            model: $name,
            fields: $parsedFields,
            searchable: $searchableList,
            sortable: $sortableList === [] ? ['created_at'] : $sortableList,
            filters: $filterList,
        );
    }

    /**
     * @return list<string>
     */
    private static function validateName(string $name): array
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]{1,39}$/', $name) !== 1 || Str::studly($name) !== $name) {
            return ["Resource name [{$name}] must be a StudlyCase singular class name, e.g. Product or BlogPost."];
        }

        if (in_array(strtolower($name), self::RESERVED_NAMES, true)) {
            return ["Resource name [{$name}] is reserved."];
        }

        if (Str::singular($name) !== $name) {
            return ["Resource name [{$name}] must be singular (e.g. ".Str::singular($name).').'];
        }

        return [];
    }

    /**
     * @return array{0: list<ResourceField>, 1: list<string>}
     */
    private static function parseFields(string $definition): array
    {
        $fields = [];
        $errors = [];
        $seen = [];

        $parts = self::splitList($definition);
        if ($parts === []) {
            return [[], ['At least one field is required, e.g. --fields="name:string:required".']];
        }

        foreach ($parts as $part) {
            if (preg_match('/^([^:]+):(string|text|integer|decimal|boolean|date|enum\(([^)]*)\))(?::(required|nullable))?$/', $part, $matches) !== 1) {
                $errors[] = "Field [{$part}] must look like name:type[:required]; types: ".implode(', ', ResourceField::TYPES).' (enum as enum(a|b)).';

                continue;
            }

            $name = $matches[1];
            $type = str_starts_with($matches[2], 'enum(') ? 'enum' : $matches[2];

            if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name) !== 1 || strlen($name) > 50) {
                $errors[] = "Field name [{$name}] must be a snake_case identifier (max 50 characters).";

                continue;
            }

            if (in_array($name, self::RESERVED_FIELDS, true)) {
                $errors[] = "Field name [{$name}] is reserved.";

                continue;
            }

            if (isset($seen[$name])) {
                $errors[] = "Field [{$name}] is defined more than once.";

                continue;
            }

            $values = [];
            if ($type === 'enum') {
                $values = array_values(array_filter(array_map('trim', explode('|', $matches[3] ?? '')), fn (string $value): bool => $value !== ''));
                $valueErrors = self::validateEnumValues($name, $values);
                if ($valueErrors !== []) {
                    $errors = [...$errors, ...$valueErrors];

                    continue;
                }
            }

            $seen[$name] = true;
            $fields[] = new ResourceField($name, $type, ($matches[4] ?? '') === 'required', $values);
        }

        return [$fields, $errors];
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function validateEnumValues(string $field, array $values): array
    {
        if ($values === []) {
            return ["Enum field [{$field}] needs at least one value, e.g. enum(draft|published)."];
        }

        $errors = [];
        foreach ($values as $value) {
            if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $value) !== 1 || strlen($value) > 32) {
                $errors[] = "Enum value [{$value}] of field [{$field}] must be a snake_case identifier (max 32 characters).";
            } elseif ($value === 'all') {
                $errors[] = "Enum value [all] of field [{$field}] is reserved for the list filter.";
            }
        }

        if (count(array_unique($values)) !== count($values)) {
            $errors[] = "Enum field [{$field}] has duplicate values.";
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function splitList(string $value): array
    {
        $items = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item !== '' && ! in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * StudlyCase plural, e.g. `BlogPosts`.
     */
    public function plural(): string
    {
        return Str::plural($this->model);
    }

    public function table(): string
    {
        return Str::snake($this->plural());
    }

    /**
     * URL segment, route name segment and page directory, e.g. `blog-posts`.
     */
    public function kebabPlural(): string
    {
        return Str::kebab($this->plural());
    }

    /**
     * i18n key under `admin.*`, e.g. `blogPosts`.
     */
    public function camelPlural(): string
    {
        return Str::camel($this->plural());
    }

    /**
     * Route parameter and PHP/TS variable name, e.g. `blogPost`.
     */
    public function variable(): string
    {
        return Str::camel($this->model);
    }

    /**
     * Sentence-case English labels, e.g. `Blog post` / `blog posts`.
     */
    public function singularLabel(): string
    {
        return Str::ucfirst(Str::lower(Str::headline($this->model)));
    }

    public function pluralLabel(): string
    {
        return Str::ucfirst(Str::lower(Str::headline($this->plural())));
    }

    /**
     * First string field; it titles rows, links and dialogs.
     */
    public function titleField(): ?ResourceField
    {
        foreach ($this->fields as $field) {
            if ($field->type === 'string') {
                return $field;
            }
        }

        return null;
    }

    public function field(string $name): ?ResourceField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<ResourceField>
     */
    public function enumFields(): array
    {
        return array_values(array_filter($this->fields, fn (ResourceField $field): bool => $field->type === 'enum'));
    }

    /**
     * @return list<ResourceField>
     */
    public function filterFields(): array
    {
        $fields = [];
        foreach ($this->filters as $name) {
            $field = $this->field($name);
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Default list order: newest first when `created_at` is sortable,
     * otherwise the first sortable column ascending.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function defaultSort(): array
    {
        return in_array('created_at', $this->sortable, true)
            ? ['created_at', 'desc']
            : [$this->sortable[0], 'asc'];
    }
}
