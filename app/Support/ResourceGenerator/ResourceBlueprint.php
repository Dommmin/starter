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
        'article', 'articletranslation', 'articleslugredirect', 'pageslugredirect', 'auditlog',
        'contactmessage', 'faq', 'menu', 'menuitem', 'menuitems', 'navigation', 'homesection',
        'homesections', 'sitesetting', 'sitesettings', 'sitesettingtranslation',
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
     * Eloquent model methods a generated relation method must not shadow.
     */
    private const array RESERVED_RELATIONS = [
        'delete', 'fill', 'fresh', 'load', 'push', 'query', 'refresh', 'replicate', 'save', 'touch', 'update',
    ];

    /**
     * Related models a generated resource must not reference: accounts are
     * managed only by the users module.
     */
    private const array BLOCKED_RELATED_MODELS = ['user'];

    /**
     * @param  list<ResourceField>  $fields
     * @param  list<string>  $searchable
     * @param  list<string>  $sortable
     * @param  list<string>  $filters
     * @param  bool  $export  Whether the list gets a CSV export (admins only).
     * @param  list<ResourceField>  $manyRelations  belongsToMany fields: no column on the
     *                                              resource table, edited with a multi-select.
     * @param  bool  $owned  Whether every record belongs to the user who created it
     *                       (`user_id`, owner-only policy, list scoped to the owner).
     */
    private function __construct(
        public string $model,
        public array $fields,
        public array $searchable,
        public array $sortable,
        public array $filters,
        public bool $export = false,
        public array $manyRelations = [],
        public bool $owned = false,
    ) {}

    /**
     * Parse and validate the command input.
     *
     * @throws InvalidArgumentException With every problem found, one per line.
     */
    public static function parse(string $name, string $fields, string $searchable = '', string $sortable = '', string $filters = '', bool $export = false, bool $owned = false): self
    {
        $errors = self::validateName($name);

        [$allFields, $fieldErrors] = self::parseFields($fields);
        $errors = [...$errors, ...$fieldErrors];

        $parsedFields = array_values(array_filter($allFields, fn (ResourceField $field): bool => $field->type !== 'belongsToMany'));
        $manyRelations = array_values(array_filter($allFields, fn (ResourceField $field): bool => $field->type === 'belongsToMany'));

        if ($allFields !== [] && $parsedFields === []) {
            $errors[] = 'At least one column field is required besides belongsToMany relations.';
        }

        if ($owned) {
            foreach ($allFields as $field) {
                if (in_array($field->name, ['user', 'user_id'], true) || $field->column() === 'user_id') {
                    $errors[] = "Field [{$field->name}] collides with the owner relation added by --owned (user, user_id).";
                }
            }
        }

        foreach ($manyRelations as $relation) {
            if ($relation->relatedModel === $name) {
                $errors[] = "Field [{$relation->name}]: a belongsToMany relation to the resource itself is not supported.";
            }
        }

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
            if (! $isSystem && ($field === null || ! $field->isSortable())) {
                $errors[] = "Sortable column [{$column}] must be a non-text field (not a relation, image or rich text) or one of: ".implode(', ', self::SYSTEM_SORT_COLUMNS).'.';
            }
        }

        $filterList = self::splitList($filters);
        foreach ($filterList as $column) {
            $field = $byName[$column] ?? null;
            if ($field === null || ! in_array($field->type, ['boolean', 'enum', 'belongsTo', 'date'], true)) {
                $errors[] = "Filter [{$column}] must be a boolean or enum field. A belongsTo relation or a date (range filter) is accepted as well.";
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
            export: $export,
            manyRelations: $manyRelations,
            owned: $owned,
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
            if (preg_match('/^(?<name>[^:]+):(?<type>string|text|integer|decimal|boolean|date|image|richtext|enum\((?<enum>[^)]*)\)|belongsToMany\((?<many>[^)]*)\)|belongsTo\((?<target>[^)]*)\))(?::(?<presence>required|nullable))?$/', $part, $matches) !== 1) {
                $errors[] = "Field [{$part}] must look like name:type[:required]; types: ".implode(', ', ResourceField::TYPES).' (enum as enum(a|b:success), belongsTo as belongsTo(Model.label_column), belongsToMany as belongsToMany(Model.label_column)).';

                continue;
            }

            $name = $matches['name'];
            $type = match (true) {
                str_starts_with($matches['type'], 'enum(') => 'enum',
                str_starts_with($matches['type'], 'belongsToMany(') => 'belongsToMany',
                str_starts_with($matches['type'], 'belongsTo(') => 'belongsTo',
                default => $matches['type'],
            };
            $required = ($matches['presence'] ?? '') === 'required';

            if (preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name) !== 1 || strlen($name) > 50) {
                $errors[] = "Field name [{$name}] must be a snake_case identifier (max 50 characters).";

                continue;
            }

            if (in_array($name, self::RESERVED_FIELDS, true)) {
                $errors[] = "Field name [{$name}] is reserved.";

                continue;
            }

            if (in_array($type, ['image', 'richtext'], true) && $required) {
                $errors[] = "Field [{$name}] of type {$type} is always optional; remove :required.";

                continue;
            }

            $values = [];
            $tones = [];
            if ($type === 'enum') {
                [$values, $tones, $valueErrors] = self::parseEnumValues($name, $matches['enum'] ?? '');
                if ($valueErrors !== []) {
                    $errors = [...$errors, ...$valueErrors];

                    continue;
                }
            }

            $relatedModel = null;
            $relatedLabel = null;
            if ($type === 'belongsTo' || $type === 'belongsToMany') {
                $target = $type === 'belongsTo' ? ($matches['target'] ?? '') : ($matches['many'] ?? '');
                $relationErrors = self::validateBelongsTo($name, $target);
                if ($relationErrors !== []) {
                    $errors = [...$errors, ...$relationErrors];

                    continue;
                }

                [$relatedModel, $relatedLabel] = explode('.', trim($target), 2);
            }

            if (in_array($type, ['belongsTo', 'belongsToMany', 'image'], true) && (str_ends_with($name, '_id') || str_ends_with($name, '_ids') || in_array(Str::camel($name), self::RESERVED_RELATIONS, true))) {
                $errors[] = "Relation field name [{$name}] must name the relation (e.g. category, not category_id) and must not shadow an Eloquent method.";

                continue;
            }

            if ($type === 'belongsToMany' && (Str::plural($name) !== $name || Str::singular($name) === $name)) {
                $errors[] = "belongsToMany field name [{$name}] must be plural, e.g. tags.";

                continue;
            }

            $field = new ResourceField($name, $type, $required, $values, $tones, $relatedModel, $relatedLabel);

            if (isset($seen[$name]) || isset($seen[$field->column()])) {
                $errors[] = "Field [{$name}] is defined more than once.";

                continue;
            }

            $seen[$name] = true;
            $seen[$field->column()] = true;
            $fields[] = $field;
        }

        return [$fields, $errors];
    }

    /**
     * Parse `draft|published:success|archived:danger` into values and their
     * badge tones.
     *
     * @return array{0: list<string>, 1: array<string, string>, 2: list<string>}
     */
    private static function parseEnumValues(string $field, string $definition): array
    {
        $values = [];
        $tones = [];
        $errors = [];

        foreach (explode('|', $definition) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            [$value, $tone] = str_contains($item, ':') ? explode(':', $item, 2) : [$item, null];
            $values[] = $value;

            if ($tone === null) {
                continue;
            }

            if (! in_array($tone, ResourceField::BADGE_TONES, true)) {
                $errors[] = "Enum tone [{$tone}] of value [{$value}] of field [{$field}] must be one of: ".implode(', ', ResourceField::BADGE_TONES).'.';

                continue;
            }

            $tones[$value] = $tone;
        }

        return [$values, $tones, [...$errors, ...self::validateEnumValues($field, $values)]];
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
     * Syntax of `belongsTo(Model.label_column)` (also used by belongsToMany). Whether the model and the
     * column exist is checked by {@see ResourceGenerator::relationErrors()}.
     *
     * @return list<string>
     */
    private static function validateBelongsTo(string $field, string $target): array
    {
        if (preg_match('/^([A-Z][A-Za-z0-9]*)\.([a-z][a-z0-9]*(?:_[a-z0-9]+)*)$/', trim($target), $matches) !== 1) {
            return ["Field [{$field}] must reference its model as belongsTo(Model.label_column), e.g. belongsTo(Category.name)."];
        }

        if (in_array(strtolower($matches[1]), self::BLOCKED_RELATED_MODELS, true)) {
            return ["Field [{$field}] cannot reference [{$matches[1]}]: user accounts are managed only by the users module."];
        }

        return [];
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
    public function fieldsOfType(string $type): array
    {
        return array_values(array_filter($this->fields, fn (ResourceField $field): bool => $field->type === $type));
    }

    /**
     * Relations whose editor offers related records: belongsTo selects and
     * belongsToMany multi-selects.
     *
     * @return list<ResourceField>
     */
    public function optionFields(): array
    {
        return [...$this->fieldsOfType('belongsTo'), ...$this->manyRelations];
    }

    public function hasType(string $type): bool
    {
        return $this->fieldsOfType($type) !== [];
    }

    /**
     * Select filters (boolean, enum, belongsTo); date fields are range filters.
     *
     * @return list<ResourceField>
     */
    public function filterFields(): array
    {
        $fields = [];
        foreach ($this->filters as $name) {
            $field = $this->field($name);
            if ($field !== null && $field->type !== 'date') {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Date fields filtered by an inclusive range (`{name}_from`, `{name}_to`).
     *
     * @return list<ResourceField>
     */
    public function dateFilterFields(): array
    {
        $fields = [];
        foreach ($this->filters as $name) {
            $field = $this->field($name);
            if ($field !== null && $field->type === 'date') {
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
