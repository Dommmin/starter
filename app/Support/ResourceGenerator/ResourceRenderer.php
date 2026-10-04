<?php

namespace App\Support\ResourceGenerator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Renders the editable stubs in `stubs/resource` into plain source files.
 *
 * Stubs hold the structure; this class only builds the per-field fragments
 * (columns, rules, casts, form fields, test values) that cannot be expressed
 * as a fixed template. Output is regular code with no runtime dependency on
 * the generator.
 */
final class ResourceRenderer
{
    /**
     * Rich text labels shared with the article and page editors (`admin.richText.*`).
     */
    private const array RICH_TEXT_LABELS = [
        'toolbar', 'heading2', 'heading3', 'heading4', 'bold', 'italic', 'strike', 'code', 'bulletList',
        'orderedList', 'blockquote', 'horizontalRule', 'link', 'unlink', 'linkDialogTitle', 'linkUrlLabel',
        'linkUrlHint', 'linkSubmit', 'linkCancel', 'linkClose', 'linkInvalid',
    ];

    /**
     * Module texts added only for the features a resource uses, per locale.
     * `{label}` is the field label, `{kebab}` the URL segment.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const array FEATURE_LABELS = [
        'en' => [
            'export' => ['export' => 'Export CSV', 'exportTooLarge' => 'The CSV export is limited to :max rows. Narrow the list with search or filters first.', 'exportFilename' => '{kebab}-:date.csv'],
            'relation' => ['noneOption' => 'None', 'optionsTruncated' => 'Only the first :max options are listed.'],
            'relationField' => ['Placeholder' => 'Select: {label}', 'Empty' => 'No options available yet: {label}.'],
            'image' => ['imageChoose' => 'Choose image', 'imageChange' => 'Change image', 'imageRemove' => 'Remove image', 'imageEmpty' => 'No image selected', 'imagePreview' => 'Selected image'],
        ],
        'pl' => [
            'export' => ['export' => 'Eksportuj CSV', 'exportTooLarge' => 'Eksport CSV obejmuje najwyżej :max wierszy. Najpierw zawęź listę wyszukiwaniem lub filtrami.', 'exportFilename' => '{kebab}-:date.csv'],
            'relation' => ['noneOption' => 'Brak', 'optionsTruncated' => 'Wyświetlono tylko pierwsze :max opcji.'],
            'relationField' => ['Placeholder' => 'Wybierz: {label}', 'Empty' => 'Brak dostępnych opcji: {label}.'],
            'image' => ['imageChoose' => 'Wybierz obraz', 'imageChange' => 'Zmień obraz', 'imageRemove' => 'Usuń obraz', 'imageEmpty' => 'Nie wybrano obrazu', 'imagePreview' => 'Wybrany obraz'],
        ],
        'de' => [
            'export' => ['export' => 'CSV exportieren', 'exportTooLarge' => 'Der CSV-Export ist auf :max Zeilen begrenzt. Grenzen Sie die Liste zuerst mit Suche oder Filtern ein.', 'exportFilename' => '{kebab}-:date.csv'],
            'relation' => ['noneOption' => 'Keine Auswahl', 'optionsTruncated' => 'Es werden nur die ersten :max Optionen angezeigt.'],
            'relationField' => ['Placeholder' => 'Auswählen: {label}', 'Empty' => 'Noch keine Optionen verfügbar: {label}.'],
            'image' => ['imageChoose' => 'Bild auswählen', 'imageChange' => 'Bild ändern', 'imageRemove' => 'Bild entfernen', 'imageEmpty' => 'Kein Bild ausgewählt', 'imagePreview' => 'Ausgewähltes Bild'],
        ],
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $stubPath,
    ) {}

    /**
     * New files keyed by path relative to the project root.
     *
     * @return array<string, string>
     */
    public function newFiles(ResourceBlueprint $resource, string $migrationTimestamp): array
    {
        $model = $resource->model;
        $plural = $resource->plural();
        $pages = 'resources/js/pages/admin/'.$resource->kebabPlural();

        $files = [
            "database/migrations/{$migrationTimestamp}_create_{$resource->table()}_table.php" => $this->php('migration', $resource, [
                'columns' => $this->migrationColumns($resource),
                'pivotTables' => $this->pivotTables($resource),
                'dropPivotTables' => $resource->manyRelations === [] ? null : implode(PHP_EOL, array_map(
                    fn (ResourceField $field): string => "        Schema::dropIfExists('{$field->pivotTable($resource->model)}');",
                    $resource->manyRelations,
                )),
            ]),
            "app/Models/{$model}.php" => $this->php('model', $resource, [
                'imports' => $this->imports([
                    ...$this->enumImportList($resource),
                    ...($this->relationFields($resource) === [] ? [] : ['Illuminate\Database\Eloquent\Relations\BelongsTo']),
                    ...($resource->manyRelations === [] ? [] : ['Illuminate\Database\Eloquent\Collection', 'Illuminate\Database\Eloquent\Relations\BelongsToMany']),
                ]),
                'propertyDocs' => $this->propertyDocs($resource),
                'fillable' => $this->quotedList(array_map(fn (ResourceField $field): string => $field->column(), $resource->fields)),
                'casts' => $this->casts($resource),
                'relations' => $this->relationMethods($resource),
            ]),
            "database/factories/{$model}Factory.php" => $this->php('factory', $resource, [
                'imports' => $this->imports([
                    ...$this->enumImportList($resource),
                    ...array_map(fn (ResourceField $field): string => 'App\\Models\\'.$field->relatedClass(), $resource->fieldsOfType('belongsTo')),
                ]),
                'definition' => $this->factoryDefinition($resource),
            ]),
            "database/seeders/{$model}Seeder.php" => $this->php('seeder', $resource),
            "app/Policies/{$model}Policy.php" => $this->php('policy', $resource, [
                'exportMethod' => $resource->export ? $this->policyExportMethod($resource) : null,
            ]),
            "app/Actions/{$plural}/Update{$model}.php" => $this->php('action.update', $resource, $this->updateActionFragments($resource)),
            "app/Http/Controllers/Admin/{$plural}/{$model}Controller.php" => $this->php('controller', $resource, $this->controllerFragments($resource)),
            "app/Http/Requests/Admin/{$plural}/List{$plural}Request.php" => $this->php('request.list', $resource, [
                'imports' => $this->imports([
                    ...($resource->filters === [] ? [] : ['Illuminate\Database\Eloquent\Builder']),
                    ...array_map(fn (ResourceField $field): string => 'App\\Models\\'.$field->relatedClass(), $this->relationFilterFields($resource)),
                    ...($this->relationFilterFields($resource) === [] ? [] : ['App\Data\Listing\RecordOptionData']),
                ]),
                'properties' => $this->listRequestProperties($resource),
                'definition' => $this->listDefinition($resource),
                'methods' => $this->listRequestMethods($resource),
            ]),
            "app/Http/Requests/Admin/{$plural}/Store{$model}Request.php" => $this->php('request.store', $resource, [
                'imports' => $this->storeRequestImports($resource),
                'prepareInput' => $this->prepareInput($resource, 'self'),
                'rules' => $this->validationRules($resource),
                'storeFieldValues' => $resource->hasType('richtext')
                    ? 'self::sanitizeRichText('.$this->validatedColumns($resource, []).')'
                    : $this->validatedColumns($resource, []),
                'relationIdsMethod' => $this->storeRelationIdsMethod($resource),
                'blankInputsMethod' => $this->blankInputsMethod($resource),
                'sanitizeMethod' => $this->sanitizeMethod($resource),
            ]),
            "app/Http/Requests/Admin/{$plural}/Update{$model}Request.php" => $this->php('request.update', $resource, [
                'prepareInput' => $this->prepareInput($resource, "Store{$model}Request"),
                'updateFieldValues' => $resource->hasType('richtext')
                    ? "Store{$model}Request::sanitizeRichText(".$this->validatedColumns($resource, ['updated_at']).')'
                    : $this->validatedColumns($resource, ['updated_at']),
                'relationIdsMethod' => $resource->manyRelations === [] ? null : PHP_EOL.<<<PHP
                        /**
                         * Related ids per belongsToMany relation, ready for `sync()`.
                         *
                         * @return {$this->relationIdsShape($resource)}
                         */
                        public function relationIds(): array
                        {
                            return Store{$model}Request::relationIdsFrom(\$this->validated());
                        }
                    PHP,
            ]),
            "app/Data/Admin/{$plural}/{$model}ListItemData.php" => $this->php('data.list-item', $resource, [
                'imports' => $this->enumImports($resource),
                'properties' => $this->dataProperties($resource, listOnly: true),
                'assignments' => $this->dataAssignments($resource, listOnly: true),
            ]),
            "app/Data/Admin/{$plural}/{$model}FormData.php" => $this->php('data.form', $resource, [
                'imports' => $this->imports([
                    ...$this->enumImportList($resource),
                    ...($resource->hasType('richtext') ? ['Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType'] : []),
                ]),
                'constructorDoc' => $this->formConstructorDoc($resource),
                'properties' => $this->dataProperties($resource, listOnly: false),
                'blankAssignments' => $this->blankAssignments($resource),
                'assignments' => $this->dataAssignments($resource, listOnly: false),
            ]),
            "app/Data/Admin/{$plural}/{$model}ListFiltersData.php" => $this->php('data.list-filters', $resource, [
                'sortType' => implode(' | ', array_map(fn (string $column): string => "'{$column}'", $resource->sortable)),
                'filterProperties' => $this->filterProperties($resource),
            ]),
            "app/Data/Admin/{$plural}/{$model}IndexData.php" => $this->php('data.index', $resource, $this->indexDataFragments($resource)),
            "app/Data/Admin/{$plural}/{$model}EditorData.php" => $this->php('data.editor', $resource, $this->editorDataFragments($resource)),
            "app/Data/Admin/{$plural}/{$model}AbilitiesData.php" => $this->php('data.abilities', $resource, [
                'exportAbility' => $resource->export ? '        public bool $export,' : null,
            ]),
            "{$pages}/index.tsx" => $this->render('react.index', $resource, [
                'primitives' => $this->indexPrimitives($resource),
                'exportImport' => $resource->export ? '    exportMethod,' : null,
                'propNames' => $this->indexPropNames($resource),
                'labelMaps' => $this->labelMaps($resource),
                'rowTitle' => $this->rowTitle($resource),
                'headerDescriptionAndActions' => $this->headerDescriptionAndActions($resource),
                'searchableProp' => $resource->searchable === [] ? null : '                searchable',
                'filterFields' => $this->filterFields($resource),
                'columns' => $this->columns($resource),
            ]),
            "{$pages}/form.tsx" => $this->render('react.form', $resource, $this->formFragments($resource)),
            "{$pages}/create.tsx" => $this->render('react.create', $resource),
            "{$pages}/edit.tsx" => $this->render('react.edit', $resource),
            "{$pages}/index.accessibility.test.tsx" => $this->render('react.index-test', $resource, [
                'items' => $this->testRows($resource),
                'filters' => $this->testFilters($resource),
                'extraProps' => $this->testExtraProps($resource),
                'canAll' => $this->abilitiesLiteral($resource, true),
                'canNone' => $this->abilitiesLiteral($resource, false),
                'firstRowTitle' => $this->testFirstRowTitle($resource),
                'exportTest' => $this->uiExportTest($resource),
                'filterTest' => $this->uiFilterTest($resource),
            ]),
            "tests/Feature/Admin/{$model}CrudTest.php" => $this->php('test.feature', $resource, [
                'imports' => $this->featureTestImports($resource),
                'payload' => $this->testPayload($resource),
                'defaultFilters' => $this->testDefaultFilters($resource),
                'editorAbilities' => $this->editorAbilities($resource),
                'searchTest' => $this->searchTest($resource),
                'blankInputsTest' => $this->blankInputsTest($resource),
                'sortColumn' => $resource->sortable[0],
                'filterTest' => $this->filterTest($resource),
                'createdExpectations' => $this->payloadExpectations($resource, '$'.$resource->variable()),
                'updatedExpectations' => $this->payloadExpectations($resource, '$'.$resource->variable()),
                'requiredFields' => $this->quotedList(array_map(
                    fn (ResourceField $field): string => $field->column(),
                    array_values(array_filter([...$resource->fields, ...$resource->manyRelations], fn (ResourceField $field): bool => $field->isRequired())),
                )),
                'invalidField' => $resource->fields[0]->column(),
                'invalidValue' => $this->invalidValue($resource->fields[0]),
                'referenceTests' => $this->referenceTests($resource),
                'manyRelationTests' => $this->manyRelationTests($resource),
                'forbiddenExport' => $this->forbiddenExport($resource),
                'exportTests' => $this->exportTests($resource),
            ]),
        ];

        foreach ($resource->enumFields() as $field) {
            $enum = $field->enumClass($model);
            $files["app/Enums/{$enum}.php"] = $this->php('enum', $resource, [
                'Enum' => $enum,
                'field' => $field->name,
                'cases' => implode(PHP_EOL, array_map(
                    fn (string $value): string => '    case '.ResourceField::enumCase($value)." = '{$value}';",
                    $field->enumValues,
                )),
            ]);
        }

        return $files;
    }

    /**
     * Route definitions appended inside the admin route group.
     */
    public function routesBlock(ResourceBlueprint $resource): string
    {
        $kebab = $resource->kebabPlural();

        return $this->render('routes', $resource, [
            'exportRoute' => ! $resource->export ? null : implode(PHP_EOL, [
                "        Route::get('/{$kebab}/export', [{$resource->model}Controller::class, 'export'])",
                "            ->name('{$kebab}.export')",
                "            ->can('export', {$resource->model}::class)",
                "            ->middleware('throttle:6,1');",
            ]),
        ]);
    }

    /**
     * The `admin.{camelPlural}` catalog entry for one locale (en, pl or de).
     */
    public function langBlock(ResourceBlueprint $resource, string $locale): string
    {
        $fieldLabels = [];
        foreach ([...$resource->fields, ...$resource->manyRelations] as $field) {
            $fieldLabels[] = "            '{$field->name}' => '{$field->label()}',";
        }

        $options = [];
        foreach ($resource->enumFields() as $field) {
            $options[] = "            '{$field->name}' => [";
            foreach ($field->enumValues as $value) {
                $options[] = "                '{$value}' => '".ResourceField::enumValueLabel($value)."',";
            }
            $options[] = '            ],';
        }

        return $this->render("lang.{$locale}", $resource, [
            'extraLabels' => $this->extraLabels($resource, $locale),
            'fieldLabels' => implode(PHP_EOL, $fieldLabels),
            'optionLabels' => $options === [] ? null : implode(PHP_EOL, ["        'options' => [", ...$options, '        ],']),
        ]);
    }

    /**
     * @param  array<string, string|null>  $fragments
     */
    private function php(string $stub, ResourceBlueprint $resource, array $fragments = []): string
    {
        return ImportSorter::sort($this->render($stub, $resource, $fragments));
    }

    /**
     * Replace `{{ name }}` placeholders. A `null` fragment removes the whole
     * line holding its placeholder; an empty string leaves an empty line.
     *
     * @param  array<string, string|null>  $fragments
     */
    private function render(string $stub, ResourceBlueprint $resource, array $fragments = []): string
    {
        $path = $this->stubPath.DIRECTORY_SEPARATOR.$stub.'.stub';

        if (! $this->files->exists($path)) {
            throw new RuntimeException("Stub [{$path}] does not exist.");
        }

        $contents = $this->files->get($path);

        foreach ($fragments as $name => $value) {
            if ($value === null) {
                $contents = (string) preg_replace('/^[ \t]*\{\{ '.preg_quote($name, '/').' \}\}\R/m', '', $contents);
            }
        }

        $replacements = [];
        foreach ([...$this->names($resource), ...$fragments] as $name => $value) {
            $replacements['{{ '.$name.' }}'] = (string) $value;
        }

        return strtr($contents, $replacements);
    }

    /**
     * @return array<string, string>
     */
    private function names(ResourceBlueprint $resource): array
    {
        return [
            'Model' => $resource->model,
            'Plural' => $resource->plural(),
            'table' => $resource->table(),
            'kebabPlural' => $resource->kebabPlural(),
            'camelPlural' => $resource->camelPlural(),
            'variable' => $resource->variable(),
            'singularLabel' => $resource->singularLabel(),
            'pluralLabel' => $resource->pluralLabel(),
            'singularLower' => Str::lower($resource->singularLabel()),
            'pluralLower' => Str::lower($resource->pluralLabel()),
        ];
    }

    /**
     * `use` lines for fully qualified class names (deduplicated), or null.
     *
     * @param  list<string>  $classes
     */
    private function imports(array $classes): ?string
    {
        $classes = array_values(array_unique($classes));

        return $classes === [] ? null : implode(PHP_EOL, array_map(fn (string $class): string => "use {$class};", $classes));
    }

    /**
     * @return list<string>
     */
    private function enumImportList(ResourceBlueprint $resource): array
    {
        return array_map(
            fn (ResourceField $field): string => 'App\\Enums\\'.$field->enumClass($resource->model),
            $resource->enumFields(),
        );
    }

    private function enumImports(ResourceBlueprint $resource): ?string
    {
        return $this->imports($this->enumImportList($resource));
    }

    /**
     * belongsTo and image fields.
     *
     * @return list<ResourceField>
     */
    private function relationFields(ResourceBlueprint $resource): array
    {
        return array_values(array_filter($resource->fields, fn (ResourceField $field): bool => $field->isRelation()));
    }

    /**
     * belongsTo fields offered as list filters.
     *
     * @return list<ResourceField>
     */
    private function relationFilterFields(ResourceBlueprint $resource): array
    {
        return array_values(array_filter($resource->filterFields(), fn (ResourceField $field): bool => $field->type === 'belongsTo'));
    }

    /**
     * Eager loads of the list and the export: the label column of every belongsTo.
     */
    private function listWith(ResourceBlueprint $resource): string
    {
        $relations = array_map(
            fn (ResourceField $field): string => "'{$field->relation()}:id,{$field->relatedLabel}'",
            $resource->fieldsOfType('belongsTo'),
        );

        return $relations === [] ? '' : '->with(['.implode(', ', $relations).'])';
    }

    private function featureTestImports(ResourceBlueprint $resource): ?string
    {
        $classes = $this->enumImportList($resource);

        if ($this->blankableFields($resource) !== []) {
            $classes[] = 'Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull';
        }

        foreach ($resource->optionFields() as $field) {
            $classes[] = 'App\\Models\\'.$field->relatedClass();
        }

        if ($resource->manyRelations !== []) {
            $classes[] = 'Illuminate\Support\Facades\DB';
        }

        if ($resource->hasType('image')) {
            $classes[] = 'App\Models\MediaAsset';
        }

        if ($resource->export) {
            $classes[] = 'App\Enums\AuditAction';
            $classes[] = 'App\Models\AuditLog';
        }

        return $this->imports($classes);
    }

    private function storeRequestImports(ResourceBlueprint $resource): ?string
    {
        $classes = $this->enumImportList($resource);

        foreach ($resource->optionFields() as $field) {
            $classes[] = 'App\\Models\\'.$field->relatedClass();
        }

        if ($resource->enumFields() !== [] || $resource->optionFields() !== []) {
            $classes[] = 'Illuminate\Validation\Rule';
        }

        if ($resource->manyRelations !== []) {
            $classes[] = 'App\Data\Listing\RecordOptionData';
        }

        if ($resource->hasType('image')) {
            $classes[] = 'App\Rules\DamImage';
        }

        if ($resource->hasType('richtext')) {
            $classes[] = 'App\Rules\RichTextDocument';
            $classes[] = 'App\Services\Content\RichTextRenderer';
        }

        return $this->imports($classes);
    }

    /**
     * Optional number, date, relation and image fields: the form sends them
     * as strings and a blank one means "no value".
     *
     * @return list<ResourceField>
     */
    private function blankableFields(ResourceBlueprint $resource): array
    {
        return array_values(array_filter(
            $resource->fields,
            fn (ResourceField $field): bool => ! $field->isRequired() && in_array($field->type, ['integer', 'decimal', 'date', 'belongsTo', 'image'], true),
        ));
    }

    /**
     * `prepareForValidation()` turning blank optional inputs into null.
     *
     * @param  string  $owner  Class holding `blankOptionalInputsAsNull()` (`self` or the store request).
     */
    private function prepareInput(ResourceBlueprint $resource, string $owner): ?string
    {
        if ($this->blankableFields($resource) === []) {
            return null;
        }

        return <<<PHP
            /**
             * Blank optional number, date, relation and image inputs mean "no value".
             */
            protected function prepareForValidation(): void
            {
                \$this->merge({$owner}::blankOptionalInputsAsNull(\$this->all()));
            }

        PHP;
    }

    private function blankInputsMethod(ResourceBlueprint $resource): ?string
    {
        $fields = $this->blankableFields($resource);
        if ($fields === []) {
            return null;
        }

        $names = $this->quotedList(array_map(fn (ResourceField $field): string => $field->column(), $fields));

        return PHP_EOL.<<<PHP
            /**
             * Null for each optional number/date/relation/image input sent as a blank string.
             *
             * @param  array<string, mixed>  \$input
             * @return array<string, null>
             */
            public static function blankOptionalInputsAsNull(array \$input): array
            {
                \$blank = [];
                foreach ([{$names}] as \$name) {
                    if (is_string(\$input[\$name] ?? null) && trim(\$input[\$name]) === '') {
                        \$blank[\$name] = null;
                    }
                }

                return \$blank;
            }
        PHP;
    }

    private function sanitizeMethod(ResourceBlueprint $resource): ?string
    {
        $fields = $resource->fieldsOfType('richtext');
        if ($fields === []) {
            return null;
        }

        $names = $this->quotedList(array_map(fn (ResourceField $field): string => $field->column(), $fields));

        return PHP_EOL.<<<PHP
            /**
             * Reduce rich text documents to the closed schema before they are
             * stored (validation already rejected unknown nodes and unsafe links).
             *
             * @param  array<string, mixed>  \$values
             * @return array<string, mixed>
             */
            public static function sanitizeRichText(array \$values): array
            {
                \$renderer = app(RichTextRenderer::class);
                foreach ([{$names}] as \$name) {
                    if (is_array(\$values[\$name] ?? null)) {
                        \$values[\$name] = \$renderer->sanitize(\$values[\$name]);
                    }
                }

                return \$values;
            }
        PHP;
    }

    private function migrationColumns(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            if ($field->isRelation()) {
                $table = $field->type === 'image' ? 'media_assets' : Str::snake(Str::pluralStudly($field->relatedClass()));
                $onDelete = $field->isRequired() ? 'restrictOnDelete' : 'nullOnDelete';
                $nullable = $field->isRequired() ? '' : '->nullable()';
                $lines[] = "            \$table->foreignId('{$field->column()}'){$nullable}->index()->constrained('{$table}')->{$onDelete}();";

                continue;
            }

            $column = match ($field->type) {
                'string' => "\$table->string('{$field->name}')",
                'text' => "\$table->text('{$field->name}')",
                'integer' => "\$table->integer('{$field->name}')",
                'decimal' => "\$table->decimal('{$field->name}', 12, 2)",
                'boolean' => "\$table->boolean('{$field->name}')->default(false)",
                'date' => "\$table->date('{$field->name}')",
                'richtext' => "\$table->json('{$field->name}')",
                default => "\$table->string('{$field->name}', 32)->default('{$field->enumValues[0]}')",
            };

            if (! $field->isRequired()) {
                $column .= '->nullable()';
            }

            if (in_array($field->name, $resource->filters, true)) {
                $column .= '->index()';
            }

            $lines[] = "            {$column};";
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Validated values of the resource columns, without `$except` and the
     * related ids of belongsToMany relations (those are synced separately).
     *
     * @param  list<string>  $except
     */
    private function validatedColumns(ResourceBlueprint $resource, array $except): string
    {
        $keys = [...$except, ...array_map(fn (ResourceField $field): string => $field->column(), $resource->manyRelations)];

        return $keys === [] ? '$this->validated()' : '$this->safe()->except(['.$this->quotedList($keys).'])';
    }

    /**
     * PHPStan shape of the related ids, e.g. `array{tags: list<int>}`.
     */
    private function relationIdsShape(ResourceBlueprint $resource): string
    {
        $entries = array_map(fn (ResourceField $field): string => "{$field->relation()}: list<int>", $resource->manyRelations);

        return 'array{'.implode(', ', $entries).'}';
    }

    private function storeRelationIdsMethod(ResourceBlueprint $resource): ?string
    {
        if ($resource->manyRelations === []) {
            return null;
        }

        $shape = $this->relationIdsShape($resource);
        $entries = implode(PHP_EOL, array_map(
            fn (ResourceField $field): string => "            '{$field->relation()}' => self::ids(\$validated['{$field->column()}'] ?? []),",
            $resource->manyRelations,
        ));

        return PHP_EOL.<<<PHP
            /**
             * Related ids per belongsToMany relation, ready for `sync()`.
             *
             * @return {$shape}
             */
            public function relationIds(): array
            {
                return self::relationIdsFrom(\$this->validated());
            }

            /**
             * @param  array<string, mixed>  \$validated
             * @return {$shape}
             */
            public static function relationIdsFrom(array \$validated): array
            {
                return [
        {$entries}
                ];
            }

            /**
             * @return list<int>
             */
            private static function ids(mixed \$value): array
            {
                return is_array(\$value) ? array_values(array_map(intval(...), \$value)) : [];
            }
        PHP;
    }

    /**
     * @return array<string, string|null>
     */
    private function updateActionFragments(ResourceBlueprint $resource): array
    {
        if ($resource->manyRelations === []) {
            return ['relationParamDoc' => null, 'relationParameter' => '', 'relationUse' => '', 'syncRelations' => null];
        }

        return [
            'relationParamDoc' => "     * @param  {$this->relationIdsShape($resource)}  \$relationIds  Related ids per belongsToMany relation.",
            'relationParameter' => ', array $relationIds',
            'relationUse' => ', $relationIds',
            'syncRelations' => implode(PHP_EOL, array_map(
                fn (ResourceField $field): string => "            \$locked->{$field->relation()}()->sync(\$relationIds['{$field->relation()}']);",
                $resource->manyRelations,
            )),
        ];
    }

    /**
     * Pivot tables of the belongsToMany relations, created right after the
     * resource table in the same migration. Both keys cascade on delete:
     * removing either record removes only the link.
     */
    private function pivotTables(ResourceBlueprint $resource): ?string
    {
        $blocks = [];
        foreach ($resource->manyRelations as $field) {
            $pivot = $field->pivotTable($resource->model);
            $ownKey = Str::snake($resource->model).'_id';
            $relatedKey = Str::snake((string) $field->relatedModel).'_id';

            $blocks[] = <<<PHP

                        Schema::create('{$pivot}', function (Blueprint \$table) {
                            \$table->foreignId('{$ownKey}')->constrained('{$resource->table()}')->cascadeOnDelete();
                            \$table->foreignId('{$relatedKey}')->index()->constrained('{$field->relatedTable()}')->cascadeOnDelete();
                            \$table->primary(['{$ownKey}', '{$relatedKey}']);
                        });
                PHP;
        }

        return $blocks === [] ? null : implode(PHP_EOL, $blocks);
    }

    private function propertyDocs(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            $type = match ($field->type) {
                'integer', 'belongsTo', 'image' => 'int',
                'boolean' => 'bool',
                'date' => 'CarbonImmutable',
                'enum' => $field->enumClass($resource->model),
                'richtext' => 'array<string, mixed>',
                default => 'string',
            };

            $lines[] = " * @property {$type}".($field->isRequired() ? '' : '|null')." \${$field->column()}";
        }

        foreach ($this->relationFields($resource) as $field) {
            $lines[] = " * @property-read {$field->relatedClass()}".($field->isRequired() ? '' : '|null')." \${$field->relation()}";
        }

        foreach ($resource->manyRelations as $field) {
            $lines[] = " * @property-read Collection<int, {$field->relatedClass()}> \${$field->relation()}";
        }

        return implode(PHP_EOL, $lines);
    }

    private function casts(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            $cast = match ($field->type) {
                'integer' => "'integer'",
                'decimal' => "'decimal:2'",
                'boolean' => "'boolean'",
                'date' => "'date'",
                'richtext' => "'array'",
                'enum' => $field->enumClass($resource->model).'::class',
                default => null,
            };

            if ($cast !== null) {
                $lines[] = "            '{$field->name}' => {$cast},";
            }
        }

        return $lines === [] ? '[]' : '['.PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL.'        ]';
    }

    private function relationMethods(ResourceBlueprint $resource): ?string
    {
        $methods = [];
        foreach ($this->relationFields($resource) as $field) {
            $class = $field->relatedClass();
            $methods[] = <<<PHP

                /**
                 * @return BelongsTo<{$class}, \$this>
                 */
                public function {$field->relation()}(): BelongsTo
                {
                    return \$this->belongsTo({$class}::class, '{$field->column()}');
                }
            PHP;
        }

        foreach ($resource->manyRelations as $field) {
            $class = $field->relatedClass();
            $methods[] = <<<PHP

                /**
                 * @return BelongsToMany<{$class}, \$this>
                 */
                public function {$field->relation()}(): BelongsToMany
                {
                    return \$this->belongsToMany({$class}::class, '{$field->pivotTable($resource->model)}');
                }
            PHP;
        }

        return $methods === [] ? null : implode(PHP_EOL, $methods);
    }

    private function factoryDefinition(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            $value = match ($field->type) {
                'string' => 'fake()->words(3, true)',
                'text' => 'fake()->paragraph()',
                'integer' => 'fake()->numberBetween(1, 1000)',
                'decimal' => 'fake()->randomFloat(2, 1, 1000)',
                'boolean' => 'fake()->boolean()',
                'date' => 'fake()->date()',
                'belongsTo' => $field->relatedClass().'::factory()',
                'image' => 'null',
                'richtext' => "['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => fake()->sentence()]]]]]",
                default => 'fake()->randomElement('.$field->enumClass($resource->model).'::cases())',
            };

            $lines[] = "            '{$field->column()}' => {$value},";
        }

        return implode(PHP_EOL, $lines);
    }

    private function policyExportMethod(ResourceBlueprint $resource): string
    {
        $lower = Str::lower($resource->pluralLabel());

        return <<<PHP

            /**
             * Determine whether the user can download {$lower} as CSV. Exports
             * leave the audited panel, so only administrators may run them.
             */
            public function export(User \$user): bool
            {
                return \$user->isAdmin();
            }
        PHP;
    }

    /**
     * @return array<string, string|null>
     */
    private function controllerFragments(ResourceBlueprint $resource): array
    {
        $belongsTo = $resource->optionFields();
        $classes = [];

        foreach ($belongsTo as $field) {
            $classes[] = 'App\\Models\\'.$field->relatedClass();
        }

        if ($belongsTo !== []) {
            $classes[] = 'App\Data\Listing\RecordOptionData';
        }

        if ($resource->export) {
            array_push($classes, ...$this->enumImportList($resource));
            array_push(
                $classes,
                'App\Actions\Audit\RecordAuditEvent',
                'App\Enums\AuditAction',
                'App\Support\Csv\CsvCell',
                'Illuminate\Http\Response as HttpResponse',
                'Illuminate\Support\Arr',
                'Symfony\Component\HttpFoundation\StreamedResponse',
            );
        }

        $indexArguments = [];
        foreach ($this->relationFilterFields($resource) as $field) {
            $indexArguments[] = "            {$field->relation()}Options: \$this->{$field->relation()}Options()[0],";
        }
        if ($resource->export) {
            $indexArguments[] = '            exportMaxRows: self::exportMaxRows(),';
        }

        $editorOptions = [];
        $editorArguments = [];
        foreach ($belongsTo as $field) {
            $relation = $field->relation();
            $editorOptions[] = "        [\${$relation}Options, \${$relation}OptionsTruncated] = \$this->{$relation}Options();";
            $editorArguments[] = "            {$relation}Options: \${$relation}Options,";
            $editorArguments[] = "            {$relation}OptionsTruncated: \${$relation}OptionsTruncated,";
        }

        $variable = '$'.$resource->variable();
        $storeStatement = "        {$variable} = {$resource->model}::query()->create(\$request->fieldValues());";
        if ($resource->manyRelations !== []) {
            $classes[] = 'Illuminate\Support\Facades\DB';
            $syncs = implode(PHP_EOL, array_map(
                fn (ResourceField $field): string => "            {$variable}->{$field->relation()}()->sync(\$relationIds['{$field->relation()}']);",
                $resource->manyRelations,
            ));
            $storeStatement = implode(PHP_EOL, [
                "        {$variable} = DB::transaction(function () use (\$request): {$resource->model} {",
                "            {$variable} = {$resource->model}::query()->create(\$request->fieldValues());",
                '            $relationIds = $request->relationIds();',
                $syncs,
                '',
                "            return {$variable};",
                '        });',
            ]);
        }

        return [
            'imports' => $this->imports($classes),
            'storeStatement' => $storeStatement,
            'updateRelationArgument' => $resource->manyRelations === [] ? '' : ', $request->relationIds()',
            'constants' => ! $resource->export ? null : implode(PHP_EOL, [
                '    /**',
                '     * Default maximum number of rows of one CSV export; a larger result',
                "     * is refused instead of being cut off (`exports.{$resource->kebabPlural()}.max_rows` overrides it).",
                '     */',
                '    public const int EXPORT_MAX_ROWS = 10_000;',
                '',
            ]),
            'with' => $this->listWith($resource),
            'indexArguments' => $indexArguments === [] ? null : implode(PHP_EOL, $indexArguments),
            'exportMethods' => $resource->export ? $this->exportMethods($resource) : null,
            'editorOptions' => $editorOptions === [] ? null : implode(PHP_EOL, $editorOptions).PHP_EOL,
            'editorArguments' => $editorArguments === [] ? null : implode(PHP_EOL, $editorArguments),
            'exportAbility' => $resource->export ? "            export: \$user?->can('export', {$resource->model}::class) ?? false," : null,
            'optionMethods' => $this->optionMethods($resource),
        ];
    }

    private function exportMethods(ResourceBlueprint $resource): string
    {
        $model = $resource->model;
        $plural = $resource->plural();
        $variable = '$'.$resource->variable();
        $keys = 'admin.'.$resource->camelPlural();
        $kebab = $resource->kebabPlural();
        $with = $this->listWith($resource);

        $headers = [];
        $cells = [];
        foreach ($resource->fields as $field) {
            if (! $field->isExported()) {
                continue;
            }

            $headers[] = "            __('{$keys}.fields.{$field->name}'),";
            $value = match ($field->type) {
                'boolean' => "{$variable}->{$field->name} ? __('{$keys}.yes') : __('{$keys}.no')",
                'date' => "{$variable}->{$field->name}".($field->isRequired() ? '->' : '?->').'toDateString()',
                'enum' => $this->enumLabelMatch($resource, $field, "{$variable}->{$field->name}"),
                'belongsTo' => "{$variable}->{$field->relation()}".($field->isRequired() ? '->' : '?->').$field->relatedLabel,
                default => "{$variable}->{$field->name}",
            };
            $cells[] = "            CsvCell::safe({$value}),";
        }

        $headers = implode(PHP_EOL, $headers);
        $cells = implode(PHP_EOL, $cells);

        return <<<PHP

            /**
             * Download the list as CSV with the same search, filters and sort as
             * the screen (administrators only, rate limited and audited). A result
             * larger than the export limit is refused with 422 instead of being cut off.
             */
            public function export(List{$plural}Request \$request, RecordAuditEvent \$recordAuditEvent): HttpResponse|StreamedResponse
            {
                \$listQuery = \$request->listQuery();
                \$validated = \$request->validated();
                \$maxRows = self::exportMaxRows();

                \$query = \$listQuery->apply({$model}::query(){$with}, \$validated);
                \$rows = (clone \$query)->reorder()->count();

                if (\$rows > \$maxRows) {
                    return response(__('{$keys}.exportTooLarge', ['max' => \$maxRows]), 422)
                        ->header('Content-Type', 'text/plain; charset=UTF-8');
                }

                \$filters = \$listQuery->filtersPayload(\$validated);
                \$recordAuditEvent->handle(AuditAction::ResourceExported, new {$model}, \$request->user(), [
                    'module' => RecordAuditEvent::change(null, '{$kebab}'),
                    'rows' => RecordAuditEvent::change(null, \$rows),
                    'filters' => RecordAuditEvent::change(null, Arr::except(\$filters, ['search'])),
                    ...(\$filters['search'] === '' ? [] : ['search' => RecordAuditEvent::redacted()]),
                ]);

                return response()->streamDownload(function () use (\$query, \$maxRows): void {
                    \$output = fopen('php://output', 'w');
                    if (\$output === false) {
                        return;
                    }

                    fwrite(\$output, "\\u{FEFF}");
                    fputcsv(\$output, \$this->exportHeader(), escape: '');
                    foreach (\$query->lazy(500)->take(\$maxRows) as {$variable}) {
                        fputcsv(\$output, \$this->exportRow({$variable}), escape: '');
                    }

                    fclose(\$output);
                }, __('{$keys}.exportFilename', ['date' => now()->format('Y-m-d')]), [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]);
            }

            /**
             * Translated column headings of the CSV export.
             *
             * @return list<string>
             */
            private function exportHeader(): array
            {
                return [
                    __('{$keys}.columnId'),
        {$headers}
                    __('{$keys}.columnCreatedAt'),
                    __('{$keys}.columnUpdatedAt'),
                ];
            }

            /**
             * One CSV row: labels instead of enum values and related ids, every
             * text cell guarded against spreadsheet formulas.
             *
             * @return list<string|int|float>
             */
            private function exportRow({$model} {$variable}): array
            {
                return [
                    {$variable}->id,
        {$cells}
                    CsvCell::safe({$variable}->created_at?->toIso8601String()),
                    CsvCell::safe({$variable}->updated_at?->toIso8601String()),
                ];
            }

            private static function exportMaxRows(): int
            {
                return config()->integer('exports.{$kebab}.max_rows', self::EXPORT_MAX_ROWS);
            }
        PHP;
    }

    /**
     * `match` from each enum case to its literal translation key, so the
     * localization gate can verify every key.
     */
    private function enumLabelMatch(ResourceBlueprint $resource, ResourceField $field, string $subject): string
    {
        $enum = $field->enumClass($resource->model);
        $arms = [];
        foreach ($field->enumValues as $value) {
            $arms[] = "                {$enum}::".ResourceField::enumCase($value)." => __('admin.{$resource->camelPlural()}.options.{$field->name}.{$value}'),";
        }

        return 'match ('.$subject.') {'.PHP_EOL.implode(PHP_EOL, $arms).PHP_EOL.'            }';
    }

    private function optionMethods(ResourceBlueprint $resource): ?string
    {
        $methods = [];
        foreach ($resource->optionFields() as $field) {
            $class = $field->relatedClass();
            $label = (string) $field->relatedLabel;
            $lower = Str::ucfirst(Str::lower(Str::headline(Str::pluralStudly($class))));

            $methods[] = <<<PHP

                /**
                 * {$lower} offered by the {$field->name} field, ordered by label and
                 * capped at RecordOptionData::LIMIT.
                 *
                 * @return array{0: list<RecordOptionData>, 1: bool} The options and whether the list was truncated.
                 */
                private function {$field->relation()}Options(): array
                {
                    \$records = {$class}::query()
                        ->orderBy('{$label}')
                        ->orderBy('id')
                        ->limit(RecordOptionData::LIMIT + 1)
                        ->get(['id', '{$label}']);

                    \$options = [];
                    foreach (\$records->take(RecordOptionData::LIMIT) as \$record) {
                        \$options[] = new RecordOptionData(\$record->id, (string) \$record->{$label});
                    }

                    return [\$options, \$records->count() > RecordOptionData::LIMIT];
                }
            PHP;
        }

        return $methods === [] ? null : implode(PHP_EOL, $methods);
    }

    /**
     * @return array<string, string|null>
     */
    private function indexDataFragments(ResourceBlueprint $resource): array
    {
        $docs = [];
        $properties = [];

        foreach ($this->relationFilterFields($resource) as $field) {
            $docs[] = "     * @param  list<RecordOptionData>  \${$field->relation()}Options  Options of the {$field->name} filter.";
            $properties[] = "        public array \${$field->relation()}Options,";
        }

        if ($resource->export) {
            $properties[] = '        public int $exportMaxRows,';
        }

        return [
            'imports' => $this->relationFilterFields($resource) === [] ? null : 'use App\Data\Listing\RecordOptionData;',
            'paramDocs' => $docs === [] ? null : implode(PHP_EOL, $docs),
            'properties' => $properties === [] ? null : implode(PHP_EOL, $properties),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function editorDataFragments(ResourceBlueprint $resource): array
    {
        $fields = $resource->optionFields();
        if ($fields === []) {
            return ['imports' => null, 'constructorDoc' => null, 'properties' => null];
        }

        $docs = ['    /**'];
        $properties = [];
        foreach ($fields as $field) {
            $relation = $field->relation();
            $docs[] = "     * @param  list<RecordOptionData>  \${$relation}Options  Choices of the {$field->name} field (at most RecordOptionData::LIMIT).";
            $docs[] = "     * @param  bool  \${$relation}OptionsTruncated  Whether more {$field->name} records exist than were sent.";
            $properties[] = "        public array \${$relation}Options,";
            $properties[] = "        public bool \${$relation}OptionsTruncated,";
        }
        $docs[] = '     */';

        return [
            'imports' => 'use App\Data\Listing\RecordOptionData;',
            'constructorDoc' => implode(PHP_EOL, $docs),
            'properties' => implode(PHP_EOL, $properties),
        ];
    }

    private function formConstructorDoc(ResourceBlueprint $resource): ?string
    {
        $fields = $resource->fieldsOfType('richtext');
        if ($fields === [] && $resource->manyRelations === []) {
            return null;
        }

        $docs = ['    /**'];
        foreach ($fields as $field) {
            $docs[] = "     * @param  array<string, mixed>|null  \${$field->property()}  Tiptap JSON document (`{ type: 'doc', content: [...] }`).";
        }
        foreach ($resource->manyRelations as $field) {
            $docs[] = "     * @param  list<int>  \${$field->property()}  Ids of the linked {$field->name}.";
        }
        $docs[] = '     */';

        return implode(PHP_EOL, $docs);
    }

    private function listRequestProperties(ResourceBlueprint $resource): ?string
    {
        $lines = [];
        foreach ($this->relationFilterFields($resource) as $field) {
            $lines[] = '    /**';
            $lines[] = '     * @var array<int, string>|null';
            $lines[] = '     */';
            $lines[] = "    private ?array \${$field->relation()}FilterValues = null;";
            $lines[] = '';
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines);
    }

    private function listRequestMethods(ResourceBlueprint $resource): ?string
    {
        $methods = [];
        foreach ($this->relationFilterFields($resource) as $field) {
            $class = $field->relatedClass();
            $relation = $field->relation();

            $methods[] = <<<PHP

                /**
                 * Ids accepted by the {$field->name} filter: the same capped, label-ordered
                 * option list as the screen (RecordOptionData::LIMIT).
                 *
                 * @return array<int, string>
                 */
                private function {$relation}FilterValues(): array
                {
                    return \$this->{$relation}FilterValues ??= {$class}::query()
                        ->orderBy('{$field->relatedLabel}')
                        ->orderBy('id')
                        ->limit(RecordOptionData::LIMIT)
                        ->pluck('id')
                        ->map(fn (mixed \$id): string => (string) \$id)
                        ->all();
                }
            PHP;
        }

        return $methods === [] ? null : implode(PHP_EOL, $methods);
    }

    private function listDefinition(ResourceBlueprint $resource): string
    {
        $lines = [];

        if ($resource->searchable !== []) {
            $lines[] = '            ->searchable('.$this->quotedList($resource->searchable).')';
        }

        [$defaultSort, $defaultDirection] = $resource->defaultSort();
        $lines[] = "            ->sortable([{$this->quotedList($resource->sortable)}], default: '{$defaultSort}', defaultDirection: '{$defaultDirection}')";

        foreach ($resource->filterFields() as $field) {
            if ($field->type === 'boolean') {
                $lines[] = "            ->filter('{$field->name}', ['all', 'yes', 'no'], default: 'all', apply: function (Builder \$query, string \$value): void {";
                $lines[] = '                match ($value) {';
                $lines[] = "                    'yes' => \$query->where('{$field->name}', true),";
                $lines[] = "                    'no' => \$query->where('{$field->name}', false),";
                $lines[] = '                    default => null,';
                $lines[] = '                };';
                $lines[] = '            })';

                continue;
            }

            if ($field->type === 'belongsTo') {
                $lines[] = "            ->filter('{$field->name}', ['all', ...\$this->{$field->relation()}FilterValues()], default: 'all', apply: function (Builder \$query, string \$value): void {";
                $lines[] = "                if (\$value !== 'all') {";
                $lines[] = "                    \$query->where('{$field->column()}', (int) \$value);";
                $lines[] = '                }';
                $lines[] = '            })';

                continue;
            }

            $lines[] = "            ->filter('{$field->name}', ['all', {$this->quotedList($field->enumValues)}], default: 'all', apply: function (Builder \$query, string \$value): void {";
            $lines[] = "                if (\$value !== 'all') {";
            $lines[] = "                    \$query->where('{$field->name}', \$value);";
            $lines[] = '                }';
            $lines[] = '            })';
        }

        return implode(PHP_EOL, $lines);
    }

    private function validationRules(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            $presence = $field->isRequired() ? "'required'" : "'nullable'";
            $rules = match ($field->type) {
                'string' => "{$presence}, 'string', 'max:255'",
                'text' => "{$presence}, 'string', 'max:20000'",
                'integer' => "{$presence}, 'integer', 'min:-2147483648', 'max:2147483647'",
                'decimal' => "{$presence}, 'numeric', 'decimal:0,2', 'min:-9999999999.99', 'max:9999999999.99'",
                'boolean' => "{$presence}, 'boolean'",
                'date' => "{$presence}, 'date_format:Y-m-d'",
                'belongsTo' => "{$presence}, 'integer', Rule::exists({$field->relatedClass()}::class, 'id')",
                'image' => "{$presence}, 'integer', new DamImage",
                'richtext' => "{$presence}, 'array', new RichTextDocument(app(RichTextRenderer::class))",
                default => "{$presence}, Rule::enum(".$field->enumClass($resource->model).'::class)',
            };

            $lines[] = "            '{$field->column()}' => [{$rules}],";
        }

        foreach ($resource->manyRelations as $field) {
            $presence = $field->isRequired() ? "'required'" : "'nullable'";
            $lines[] = "            '{$field->column()}' => [{$presence}, 'array', 'list', 'max:'.RecordOptionData::LIMIT],";
            $lines[] = "            '{$field->column()}.*' => ['integer', 'distinct', Rule::exists({$field->relatedClass()}::class, 'id')],";
        }

        return implode(PHP_EOL, $lines);
    }

    private function dataProperties(ResourceBlueprint $resource, bool $listOnly): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            if ($listOnly && ! $field->isListed()) {
                continue;
            }

            if ($listOnly && $field->type === 'belongsTo') {
                $lines[] = "        public ?string \${$field->relation()}Label,";

                continue;
            }

            if ($field->type === 'richtext') {
                $lines[] = "        #[LiteralTypeScriptType('{ [key: string]: unknown } | null')]";
                $lines[] = "        public ?array \${$field->property()},";

                continue;
            }

            $type = match ($field->type) {
                'integer', 'belongsTo', 'image' => 'int',
                'boolean' => 'bool',
                'enum' => $field->enumClass($resource->model),
                default => 'string',
            };

            $nullable = in_array($field->type, ['boolean', 'enum'], true)
                ? false
                : ! $listOnly || ! $field->isRequired();

            $lines[] = '        public '.($nullable ? '?' : '')."{$type} \${$field->property()},";
        }

        if (! $listOnly) {
            foreach ($resource->manyRelations as $field) {
                $lines[] = "        public array \${$field->property()},";
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function dataAssignments(ResourceBlueprint $resource, bool $listOnly): string
    {
        $variable = '$'.$resource->variable();
        $lines = [];
        foreach ($resource->fields as $field) {
            if ($listOnly && ! $field->isListed()) {
                continue;
            }

            if ($listOnly && $field->type === 'belongsTo') {
                $relation = "{$variable}->{$field->relation()}";
                $lines[] = $field->isRequired()
                    ? "            {$field->relation()}Label: (string) {$relation}->{$field->relatedLabel},"
                    : "            {$field->relation()}Label: {$relation} === null ? null : (string) {$relation}->{$field->relatedLabel},";

                continue;
            }

            $value = "{$variable}->{$field->column()}";
            if ($field->type === 'date') {
                $value .= ($field->isRequired() ? '->' : '?->').'toDateString()';
            }

            $lines[] = "            {$field->property()}: {$value},";
        }

        if (! $listOnly) {
            foreach ($resource->manyRelations as $field) {
                $key = $field->relatedTable().'.id';
                $lines[] = "            {$field->property()}: array_values(array_map(intval(...), {$variable}->{$field->relation()}()->orderBy('{$key}')->pluck('{$key}')->all())),";
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function blankAssignments(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            $value = match ($field->type) {
                'boolean' => 'false',
                'enum' => $field->enumClass($resource->model).'::'.ResourceField::enumCase($field->enumValues[0]),
                default => 'null',
            };

            $lines[] = "            {$field->property()}: {$value},";
        }

        foreach ($resource->manyRelations as $field) {
            $lines[] = "            {$field->property()}: [],";
        }

        return implode(PHP_EOL, $lines);
    }

    private function filterProperties(ResourceBlueprint $resource): ?string
    {
        $lines = [];
        foreach ($resource->filterFields() as $field) {
            if ($field->type === 'belongsTo') {
                $lines[] = "        /** `all` or the id of a {$field->name} record. */";
                $lines[] = "        public string \${$field->name},";

                continue;
            }

            $values = $field->type === 'boolean' ? ['all', 'yes', 'no'] : ['all', ...$field->enumValues];
            $lines[] = '        #[LiteralTypeScriptType("'.implode(' | ', array_map(fn (string $value): string => "'{$value}'", $values)).'")]';
            $lines[] = "        public string \${$field->name},";
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines);
    }

    private function indexPrimitives(ResourceBlueprint $resource): string
    {
        $primitives = ['Button', 'ConfirmDialog', 'Link', 'ResourceTable'];

        foreach ($resource->fields as $field) {
            if (in_array($field->type, ['boolean', 'enum'], true)) {
                $primitives[] = 'Badge';
            }
        }

        $primitives = array_unique($primitives);
        sort($primitives);

        return implode(PHP_EOL, array_map(fn (string $name): string => "    {$name},", $primitives));
    }

    private function indexPropNames(ResourceBlueprint $resource): string
    {
        $names = ['items', 'pagination', 'filters', 'can'];

        foreach ($this->relationFilterFields($resource) as $field) {
            $names[] = "{$field->relation()}Options";
        }

        if ($resource->export) {
            $names[] = 'exportMaxRows';
        }

        return implode(', ', $names);
    }

    private function labelMaps(ResourceBlueprint $resource): string
    {
        $blocks = [];

        if ($resource->export) {
            $blocks[] = '    const isExportTooLarge = pagination.total > exportMaxRows;';
        }

        foreach ($resource->enumFields() as $field) {
            $lines = ["    const {$field->property()}Labels: Record<App.Enums.{$field->enumClass($resource->model)}, string> = {"];
            foreach ($field->enumValues as $value) {
                $lines[] = "        {$value}: t('admin.{$resource->camelPlural()}.options.{$field->name}.{$value}'),";
            }
            $lines[] = '    };';
            $blocks[] = implode(PHP_EOL, $lines);

            if ($field->enumTones !== []) {
                $lines = ["    const {$field->property()}Tones = {"];
                foreach ($field->enumValues as $value) {
                    $lines[] = "        {$value}: '{$field->toneOf($value)}',";
                }
                $lines[] = '    } as const;';
                $blocks[] = implode(PHP_EOL, $lines);
            }
        }

        return $blocks === [] ? '' : PHP_EOL.implode(PHP_EOL.PHP_EOL, $blocks).PHP_EOL;
    }

    private function rowTitle(ResourceBlueprint $resource): string
    {
        $untitled = "t('admin.{$resource->camelPlural()}.untitled', { id: row.id })";
        $title = $resource->titleField();

        return $title === null ? $untitled : "row.{$title->property()} || {$untitled}";
    }

    private function headerDescriptionAndActions(ResourceBlueprint $resource): string
    {
        $keys = 'admin.'.$resource->camelPlural();

        if (! $resource->export) {
            return implode(PHP_EOL, [
                "                    description: t('{$keys}.description'),",
                '                    actions: can.create ? (',
                '                        <Button href={create()}>',
                "                            {t('{$keys}.create')}",
                '                        </Button>',
                '                    ) : undefined,',
            ]);
        }

        return <<<TSX
                            description:
                                can.export && isExportTooLarge
                                    ? `\${t('{$keys}.description')} \${t('{$keys}.exportTooLarge', { max: exportMaxRows })}`
                                    : t('{$keys}.description'),
                            actions: (
                                <>
                                    {can.export &&
                                        (isExportTooLarge ? (
                                            <Button variant="outline" disabled>
                                                {t('{$keys}.export')}
                                            </Button>
                                        ) : (
                                            <Button
                                                variant="outline"
                                                href={exportMethod({ query: { ...filters } })}
                                                download
                                            >
                                                {t('{$keys}.export')}
                                            </Button>
                                        ))}
                                    {can.create && (
                                        <Button href={create()}>
                                            {t('{$keys}.create')}
                                        </Button>
                                    )}
                                </>
                            ),
        TSX;
    }

    private function filterFields(ResourceBlueprint $resource): ?string
    {
        $keys = 'admin.'.$resource->camelPlural();
        $lines = [];

        foreach ($resource->filterFields() as $field) {
            $lines[] = '                    {';
            $lines[] = "                        name: '{$field->name}',";
            $lines[] = "                        label: t('{$keys}.fields.{$field->name}'),";
            $lines[] = "                        defaultValue: 'all',";
            $lines[] = '                        options: [';
            $lines[] = "                            { value: 'all', label: t('{$keys}.filterAll') },";

            if ($field->type === 'boolean') {
                $lines[] = "                            { value: 'yes', label: t('{$keys}.yes') },";
                $lines[] = "                            { value: 'no', label: t('{$keys}.no') },";
            } elseif ($field->type === 'belongsTo') {
                $lines[] = "                            ...{$field->relation()}Options.map((option) => ({";
                $lines[] = '                                value: String(option.id),';
                $lines[] = '                                label: option.label,';
                $lines[] = '                            })),';
            } else {
                foreach ($field->enumValues as $value) {
                    $lines[] = "                            { value: '{$value}', label: {$field->property()}Labels.{$value} },";
                }
            }

            $lines[] = '                        ],';
            $lines[] = '                    },';
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines);
    }

    private function columns(ResourceBlueprint $resource): string
    {
        $keys = 'admin.'.$resource->camelPlural();
        $titleField = $resource->titleField();
        $link = '(<Link href={edit(row.id)} tone="primary">{rowTitle(row)}</Link>)';
        $columns = [];

        if ($titleField === null || in_array('id', $resource->sortable, true)) {
            $columns[] = $this->column('id', "t('{$keys}.columnId')", in_array('id', $resource->sortable, true), $titleField === null ? $link : 'row.id', $titleField === null ? 'primary' : 'optional');
        }

        foreach ($resource->fields as $field) {
            if (! $field->isListed()) {
                continue;
            }

            $property = "row.{$field->property()}";
            $render = match (true) {
                $field === $titleField => $link,
                $field->type === 'boolean' => "(<Badge tone={{$property} ? 'success' : 'neutral'}>{{$property} ? t('{$keys}.yes') : t('{$keys}.no')}</Badge>)",
                $field->type === 'enum' && $field->enumTones !== [] => "(<Badge tone={{$field->property()}Tones[{$property}]}>{{$field->property()}Labels[{$property}]}</Badge>)",
                $field->type === 'enum' => "(<Badge tone=\"neutral\">{{$field->property()}Labels[{$property}]}</Badge>)",
                $field->type === 'belongsTo' => "row.{$field->relation()}Label ?? '—'",
                $field->isRequired() => $property,
                default => "{$property} ?? '—'",
            };

            $priority = match (true) {
                $field === $titleField => 'primary',
                in_array($field->type, ['boolean', 'enum'], true) => 'status',
                default => 'secondary',
            };

            $columns[] = $this->column($field->name, "t('{$keys}.fields.{$field->name}')", in_array($field->name, $resource->sortable, true), $render, $priority);
        }

        $columns[] = $this->column('created_at', "t('{$keys}.columnCreatedAt')", in_array('created_at', $resource->sortable, true), "row.createdAt ? formatDate(row.createdAt) : '—'", 'optional');

        if (in_array('updated_at', $resource->sortable, true)) {
            $columns[] = $this->column('updated_at', "t('{$keys}.columnUpdatedAt')", true, "row.updatedAt ? formatDate(row.updatedAt) : '—'", 'optional');
        }

        return implode(PHP_EOL, $columns);
    }

    /**
     * @param  'primary'|'status'|'secondary'|'optional'  $priority  Responsive role (`DataTableColumnPriority`).
     */
    private function column(string $key, string $label, bool $sortable, string $render, string $priority): string
    {
        return implode(PHP_EOL, array_filter([
            '                    {',
            "                        key: '{$key}',",
            "                        priority: '{$priority}',",
            "                        label: {$label},",
            $sortable ? '                        sortable: true,' : null,
            "                        render: (row) => {$render},",
            '                    },',
        ]));
    }

    /**
     * @return array<string, string|null>
     */
    private function formFragments(ResourceBlueprint $resource): array
    {
        $richText = $resource->fieldsOfType('richtext');
        $needsPicker = $richText !== [] || $resource->hasType('image');
        $optionalRelations = array_filter($resource->fieldsOfType('belongsTo'), fn (ResourceField $field): bool => ! $field->isRequired());

        $transform = [];
        foreach ($resource->fields as $field) {
            if ($field->type === 'richtext') {
                $transform[] = "            {$field->column()}: documentPayload(data.{$field->column()}),";
            } elseif ($field->type === 'belongsTo' && ! $field->isRequired()) {
                $transform[] = "            {$field->column()}: data.{$field->column()} === NONE ? '' : data.{$field->column()},";
            }
        }

        return [
            'coreImport' => $richText === [] ? null : "import type { FormDataConvertible } from '@inertiajs/core';",
            'typeImports' => $richText === [] ? null : '    type RichTextDocument,'.PHP_EOL.'    type RichTextFieldLabels,',
            'pickerImport' => $needsPicker ? "import { useMediaImagePicker } from '@/hooks/use-media-image-picker';" : null,
            'valueTypes' => $this->formValueTypes($resource),
            'stateType' => $this->formStateType($resource),
            'fieldNames' => $this->quotedList(array_map(fn (ResourceField $field): string => $field->column(), [...$resource->fields, ...$resource->manyRelations])),
            'multiSelectImport' => $resource->manyRelations === [] ? null : "import { multiSelectLabels } from '@/lib/multi-select-labels';",
            'helpers' => $this->formHelpers($resource, $optionalRelations !== []),
            'toState' => $this->formToState($resource),
            'pickerSetup' => $this->formPickerSetup($resource),
            'transformFields' => $transform === [] ? null : implode(PHP_EOL, $transform),
            'formValues' => $richText === [] ? 'form.data' : 'toFormValues(form.data)',
            'changeValue' => $richText === [] ? 'value' : "typeof value === 'object' ? JSON.stringify(value) : value",
            'formFields' => $this->formFields($resource),
        ];
    }

    private function formValueTypes(ResourceBlueprint $resource): string
    {
        return implode(PHP_EOL, array_map(fn (ResourceField $field): string => "    {$field->column()}: ".match ($field->type) {
            'boolean' => 'boolean',
            'enum' => 'App.Enums.'.$field->enumClass($resource->model),
            'richtext' => 'RichTextDocument',
            'belongsToMany' => 'string[]',
            default => 'string',
        }.';', [...$resource->fields, ...$resource->manyRelations]));
    }

    private function formStateType(ResourceBlueprint $resource): string
    {
        $richText = array_map(fn (ResourceField $field): string => $field->column(), $resource->fieldsOfType('richtext'));

        if ($richText === []) {
            return "/** Form state; identical to the rendered values (no rich text). */\ntype FormState = FormValues;";
        }

        $omitted = implode(' | ', array_map(fn (string $name): string => "'{$name}'", $richText));
        $serialised = implode('; ', array_map(fn (string $name): string => "{$name}: string", $richText));

        return <<<TS
        /**
         * Request-ready form state: rich text is kept serialised because Inertia
         * form data must be JSON-convertible, while Tiptap node attributes are open.
         */
        type FormState = Omit<FormValues, {$omitted}> & { {$serialised} };
        TS;
    }

    private function formHelpers(ResourceBlueprint $resource, bool $hasOptionalRelations): ?string
    {
        $blocks = [];

        if ($hasOptionalRelations) {
            $blocks[] = <<<'TS'
            /** Select value meaning "no related record" (Radix selects cannot use `''`). */
            const NONE = 'none';
            TS;
        }

        $richText = $resource->fieldsOfType('richtext');
        if ($richText !== []) {
            $parsed = implode(PHP_EOL, array_map(
                fn (ResourceField $field): string => "        {$field->column()}: parseDocument(state.{$field->column()}),",
                $richText,
            ));

            $blocks[] = <<<TS
            const EMPTY_DOCUMENT: RichTextDocument = { type: 'doc', content: [] };

            function parseDocument(serialized: string): RichTextDocument {
                const parsed: unknown = JSON.parse(serialized);

                return typeof parsed === 'object' &&
                    parsed !== null &&
                    'type' in parsed &&
                    parsed.type === 'doc'
                    ? (parsed as RichTextDocument)
                    : EMPTY_DOCUMENT;
            }

            /** A document with nothing but empty paragraphs is sent as "no content". */
            function documentPayload(serialized: string): FormDataConvertible {
                const isEmpty = (parseDocument(serialized).content ?? []).every(
                    (node) =>
                        node.type === 'paragraph' && (node.content ?? []).length === 0,
                );
                const document: FormDataConvertible = JSON.parse(serialized);

                return isEmpty ? null : document;
            }

            function toFormValues(state: FormState): FormValues {
                return {
                    ...state,
            {$parsed}
                };
            }
            TS;
        }

        return $blocks === [] ? null : implode(PHP_EOL.PHP_EOL, $blocks).PHP_EOL;
    }

    private function formToState(ResourceBlueprint $resource): string
    {
        return implode(PHP_EOL, array_map(function (ResourceField $field): string {
            $property = "record.{$field->property()}";
            $value = match ($field->type) {
                'boolean', 'enum' => $property,
                'integer', 'belongsTo', 'image' => "{$property} === null ? '' : String({$property})",
                'richtext' => "JSON.stringify({$property} ?? EMPTY_DOCUMENT)",
                default => "{$property} ?? ''",
            };

            return "        {$field->column()}: {$value},";
        }, $resource->fields)).implode('', array_map(
            fn (ResourceField $field): string => PHP_EOL."        {$field->column()}: record.{$field->property()}.map(String),",
            $resource->manyRelations,
        ));
    }

    private function formPickerSetup(ResourceBlueprint $resource): ?string
    {
        $keys = 'admin.'.$resource->camelPlural();
        $lines = [];

        if ($resource->hasType('image') || $resource->hasType('richtext')) {
            $lines[] = '    const imagePicker = useMediaImagePicker();';
        }

        if ($resource->hasType('image')) {
            $lines[] = '    const imageFieldPicker = {';
            $lines[] = '        ...imagePicker,';
            $lines[] = '        labels: {';
            $lines[] = '            ...imagePicker.labels,';
            $lines[] = "            dialogTitle: t('{$keys}.imageChoose'),";
            $lines[] = "            submit: t('{$keys}.imageChoose'),";
            $lines[] = '        },';
            $lines[] = '    };';
        }

        if ($resource->hasType('richtext')) {
            $lines[] = '    const richTextLabels: RichTextFieldLabels = {';
            foreach (self::RICH_TEXT_LABELS as $label) {
                $lines[] = "        {$label}: t('admin.richText.{$label}'),";
            }
            $lines[] = '    };';
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function formFields(ResourceBlueprint $resource): string
    {
        $keys = 'admin.'.$resource->camelPlural();
        $blocks = [];

        foreach ([...$resource->fields, ...$resource->manyRelations] as $field) {
            $indent = str_repeat(' ', 32);
            $lines = ['                            {'];
            $lines[] = $indent.'type: '.match ($field->type) {
                'text' => "'textarea'",
                'integer', 'decimal' => "'number'",
                'date' => "'date'",
                'boolean' => "'switch'",
                'enum', 'belongsTo' => "'select'",
                'belongsToMany' => "'multiSelect'",
                'image' => "'image'",
                'richtext' => "'richText'",
                default => "'text'",
            }.',';
            $lines[] = $indent."name: '{$field->column()}',";
            $lines[] = $indent."label: t('{$keys}.fields.{$field->name}'),";

            if ($field->type === 'text') {
                $lines[] = $indent.'rows: 5,';
            }

            if ($field->type === 'integer') {
                $lines[] = $indent.'step: 1,';
            }

            if ($field->type === 'decimal') {
                $lines[] = $indent.'step: 0.01,';
            }

            if ($field->type === 'enum') {
                $lines[] = $indent.'options: [';
                foreach ($field->enumValues as $value) {
                    $lines[] = $indent."    { value: '{$value}', label: t('{$keys}.options.{$field->name}.{$value}') },";
                }
                $lines[] = $indent.'],';
            }

            if ($field->type === 'belongsToMany') {
                $options = "editor.{$field->relation()}Options";
                $lines[] = $indent."placeholder: t('{$keys}.{$field->relation()}Placeholder'),";
                $lines[] = $indent."options: {$options}.map((option) => ({";
                $lines[] = $indent.'    value: String(option.id),';
                $lines[] = $indent.'    label: option.label,';
                $lines[] = $indent.'})),';
                $lines[] = $indent.'labels: multiSelectLabels(t),';
                $lines[] = $indent."hint: editor.{$field->relation()}OptionsTruncated";
                $lines[] = $indent."    ? t('{$keys}.optionsTruncated', { max: {$options}.length })";
                $lines[] = $indent."    : {$options}.length === 0";
                $lines[] = $indent."      ? t('{$keys}.{$field->relation()}Empty')";
                $lines[] = $indent.'      : undefined,';
            }

            if ($field->type === 'belongsTo') {
                $options = "editor.{$field->relation()}Options";
                $lines[] = $indent."placeholder: t('{$keys}.{$field->relation()}Placeholder'),";
                if ($field->isRequired()) {
                    $lines[] = $indent."options: {$options}.map((option) => ({";
                    $lines[] = $indent.'    value: String(option.id),';
                    $lines[] = $indent.'    label: option.label,';
                    $lines[] = $indent.'})),';
                } else {
                    $lines[] = $indent.'options: [';
                    $lines[] = $indent."    { value: NONE, label: t('{$keys}.noneOption') },";
                    $lines[] = $indent."    ...{$options}.map((option) => ({";
                    $lines[] = $indent.'        value: String(option.id),';
                    $lines[] = $indent.'        label: option.label,';
                    $lines[] = $indent.'    })),';
                    $lines[] = $indent.'],';
                }
                $lines[] = $indent."hint: editor.{$field->relation()}OptionsTruncated";
                $lines[] = $indent."    ? t('{$keys}.optionsTruncated', { max: {$options}.length })";
                $lines[] = $indent."    : {$options}.length === 0";
                $lines[] = $indent."      ? t('{$keys}.{$field->relation()}Empty')";
                $lines[] = $indent.'      : undefined,';
            }

            if ($field->type === 'image') {
                $lines[] = $indent.'picker: imageFieldPicker,';
                $lines[] = $indent.'labels: {';
                foreach (['choose' => 'imageChoose', 'change' => 'imageChange', 'remove' => 'imageRemove', 'empty' => 'imageEmpty', 'preview' => 'imagePreview'] as $label => $key) {
                    $lines[] = $indent."    {$label}: t('{$keys}.{$key}'),";
                }
                $lines[] = $indent.'},';
            }

            if ($field->type === 'richtext') {
                $lines[] = $indent.'labels: richTextLabels,';
                $lines[] = $indent.'imagePicker,';
            }

            if ($field->isRequired() && $field->type !== 'boolean') {
                $lines[] = $indent.'required: true,';
            }

            $lines[] = '                            },';
            $blocks[] = implode(PHP_EOL, $lines);
        }

        return implode(PHP_EOL, $blocks);
    }

    /**
     * Module-level texts of the optional features (export, relations, images)
     * for one locale; null when the resource uses none of them.
     */
    private function extraLabels(ResourceBlueprint $resource, string $locale): ?string
    {
        $labels = self::FEATURE_LABELS[$locale];
        $entries = [];

        if ($resource->export) {
            $entries = [...$entries, ...$labels['export']];
        }

        $belongsTo = $resource->optionFields();
        if ($belongsTo !== []) {
            $entries = [...$entries, ...$labels['relation']];
        }

        foreach ($belongsTo as $field) {
            foreach ($labels['relationField'] as $suffix => $text) {
                $entries[$field->relation().$suffix] = strtr($text, ['{label}' => $field->label()]);
            }
        }

        if ($resource->hasType('image')) {
            $entries = [...$entries, ...$labels['image']];
        }

        if ($entries === []) {
            return null;
        }

        $lines = [];
        foreach ($entries as $key => $text) {
            $text = strtr($text, ['{kebab}' => $resource->kebabPlural()]);
            $lines[] = "        '{$key}' => '".str_replace("'", "\\'", $text)."',";
        }

        return implode(PHP_EOL, $lines);
    }

    private function testRows(ResourceBlueprint $resource): string
    {
        $rows = [];
        foreach ([7 => 0, 8 => 1] as $id => $variant) {
            $lines = ['            {', "                id: {$id},"];
            foreach ($resource->fields as $field) {
                if (! $field->isListed()) {
                    continue;
                }

                if ($field->type === 'belongsTo') {
                    $lines[] = "                {$field->relation()}Label: ".($variant === 0 ? "'First {$field->label()}'" : 'null').',';

                    continue;
                }

                $lines[] = "                {$field->property()}: {$this->sampleTsValue($field, $variant)},";
            }
            $lines[] = "                createdAt: '2026-09-01T10:00:00Z',";
            $lines[] = "                updatedAt: '2026-09-01T10:00:00Z',";
            $lines[] = '            },';
            $rows[] = implode(PHP_EOL, $lines);
        }

        return implode(PHP_EOL, $rows);
    }

    private function sampleTsValue(ResourceField $field, int $variant): string
    {
        $ordinal = $variant === 0 ? 'First' : 'Second';

        return match ($field->type) {
            'string' => "'{$ordinal} ".Str::lower($field->label())."'",
            'integer' => (string) ($variant + 1),
            'decimal' => $variant === 0 ? "'10.00'" : "'20.00'",
            'boolean' => $variant === 0 ? 'true' : 'false',
            'date' => $variant === 0 ? "'2026-01-15'" : "'2026-02-15'",
            default => "'".($variant === 0 ? $field->enumValues[0] : $field->enumValues[count($field->enumValues) - 1])."'",
        };
    }

    private function testFilters(ResourceBlueprint $resource): string
    {
        [$sort, $direction] = $resource->defaultSort();
        $lines = [
            "            search: '',",
            "            sort: '{$sort}',",
            "            direction: '{$direction}',",
        ];

        foreach ($resource->filterFields() as $field) {
            $lines[] = "            {$field->name}: 'all',";
        }

        return implode(PHP_EOL, $lines);
    }

    private function testExtraProps(ResourceBlueprint $resource): ?string
    {
        $lines = [];

        foreach ($this->relationFilterFields($resource) as $field) {
            $lines[] = "        {$field->relation()}Options: [{ id: 3, label: 'First {$field->label()}' }],";
        }

        if ($resource->export) {
            $lines[] = '        exportMaxRows: 10000,';
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines);
    }

    private function abilitiesLiteral(ResourceBlueprint $resource, bool $value): string
    {
        $flag = $value ? 'true' : 'false';

        return $resource->export
            ? "{ create: {$flag}, delete: {$flag}, export: {$flag} }"
            : "{ create: {$flag}, delete: {$flag} }";
    }

    private function testFirstRowTitle(ResourceBlueprint $resource): string
    {
        $title = $resource->titleField();

        return $title === null
            ? "admin.{$resource->camelPlural()}.untitled"
            : 'First '.Str::lower($title->label());
    }

    private function uiExportTest(ResourceBlueprint $resource): ?string
    {
        if (! $resource->export) {
            return null;
        }

        $keys = 'admin.'.$resource->camelPlural();
        $kebab = $resource->kebabPlural();
        $canAll = $this->abilitiesLiteral($resource, true);
        $withoutExport = '{ create: true, delete: true, export: false }';

        return <<<TS

            it('links the CSV export with the current filters only for exporters', async () => {
                pageProps = makeProps({$canAll});
                let container = await render();

                const exportLink = Array.from(container.querySelectorAll('a')).find(
                    (link) => link.textContent === '{$keys}.export',
                );
                expect(exportLink?.hasAttribute('download')).toBe(true);
                expect(exportLink?.getAttribute('href')).toContain('/admin/{$kebab}/export?');
                expect(exportLink?.getAttribute('href')).toContain('sort=');

                await unmountAll();

                pageProps = makeProps({$withoutExport});
                container = await render();

                expect(container.textContent).not.toContain('{$keys}.export');
            });

            it('disables the export when the filtered list exceeds the limit', async () => {
                pageProps = { ...makeProps({$canAll}), exportMaxRows: 1 };
                const container = await render();

                const exportButton = Array.from(
                    container.querySelectorAll('button'),
                ).find((button) => button.textContent === '{$keys}.export');
                expect(exportButton?.disabled).toBe(true);
                expect(container.textContent).toContain('{$keys}.exportTooLarge');
            });
        TS;
    }

    private function uiFilterTest(ResourceBlueprint $resource): ?string
    {
        $field = $resource->filterFields()[0] ?? null;
        if ($field === null) {
            return null;
        }

        [$sort, $direction] = $resource->defaultSort();
        $active = match ($field->type) {
            'boolean' => 'yes',
            'belongsTo' => '3',
            default => $field->enumValues[0],
        };
        $keys = 'admin.'.$resource->camelPlural();
        $canAll = $this->abilitiesLiteral($resource, true);

        return <<<TS

            it('clears the filters to the server defaults', async () => {
                pageProps = makeProps({$canAll});
                pageProps.filters = {
                    ...pageProps.filters,
                    {$field->name}: '{$active}',
                };
                const container = await render();

                const clearButton = Array.from(
                    container.querySelectorAll('button'),
                ).find((button) => button.textContent === '{$keys}.clearFilters');

                await act(async () => {
                    clearButton?.click();
                });

                expect(getMock).toHaveBeenCalledTimes(1);
                expect(getMock.mock.calls[0][1]).toMatchObject({
                    {$field->name}: 'all',
                    sort: '{$sort}',
                    direction: '{$direction}',
                    page: 1,
                });
            });
        TS;
    }

    private function testPayload(ResourceBlueprint $resource): string
    {
        return implode(PHP_EOL, [
            ...array_map(
                fn (ResourceField $field): string => "        '{$field->column()}' => {$this->samplePhpValue($resource, $field, raw: true)},",
                $resource->fields,
            ),
            ...array_map(
                fn (ResourceField $field): string => "        '{$field->column()}' => [{$field->relatedClass()}::factory()->create()->id],",
                $resource->manyRelations,
            ),
        ]);
    }

    /**
     * Sync on create and update, the editor payload, rejected ids and the
     * pivot cascade of every belongsToMany relation.
     */
    private function manyRelationTests(ResourceBlueprint $resource): ?string
    {
        $tests = [];
        $model = $resource->model;
        $variable = $resource->variable();
        $kebab = $resource->kebabPlural();

        foreach ($resource->manyRelations as $field) {
            $class = $field->relatedClass();
            $relation = $field->relation();
            $key = $field->column();
            $qualifiedId = $field->relatedTable().'.id';
            $pivot = $field->pivotTable($model);

            $tests[] = <<<PHP

            test('the {$field->name} are synced on create, replaced on update and unlinked with their record', function () {
                \$editor = User::factory()->editor()->create();
                [\$first, \$second] = {$class}::factory()->count(2)->create();

                \$this->actingAs(\$editor)->post(route('admin.{$kebab}.store'), {$variable}Payload(['{$key}' => [\$first->id]]))
                    ->assertSessionHasNoErrors();
                \${$variable} = {$model}::query()->sole();
                expect(\${$variable}->{$relation}()->pluck('{$qualifiedId}')->all())->toBe([\$first->id]);

                \$this->actingAs(\$editor)->get(route('admin.{$kebab}.edit', \${$variable}))
                    ->assertInertia(fn (Assert \$inertia) => \$inertia
                        ->where('{$variable}.{$field->property()}', [\$first->id])
                        ->has('{$relation}Options', {$class}::query()->count())
                    );

                \$this->actingAs(\$editor)->put(route('admin.{$kebab}.update', \${$variable}), {$variable}Payload([
                    '{$key}' => [\$second->id],
                    'updated_at' => \${$variable}->updated_at?->toIso8601String(),
                ]))->assertSessionHasNoErrors();

                expect(\${$variable}->{$relation}()->pluck('{$qualifiedId}')->all())->toBe([\$second->id]);

                \$second->delete();

                expect(DB::table('{$pivot}')->count())->toBe(0)
                    ->and({$model}::query()->whereKey(\${$variable}->id)->exists())->toBeTrue();
            });

            test('unknown or repeated {$field->name} ids are rejected without linking anything', function () {
                \$editor = User::factory()->editor()->create();
                \$related = {$class}::factory()->create();

                \$this->actingAs(\$editor)->post(route('admin.{$kebab}.store'), {$variable}Payload(['{$key}' => [\$related->id + 1000]]))
                    ->assertSessionHasErrors(['{$key}.0']);
                \$this->actingAs(\$editor)->post(route('admin.{$kebab}.store'), {$variable}Payload(['{$key}' => [\$related->id, \$related->id]]))
                    ->assertSessionHasErrors(['{$key}.0']);

                expect({$model}::query()->count())->toBe(0)
                    ->and(DB::table('{$pivot}')->count())->toBe(0);
            });
            PHP;
        }

        return $tests === [] ? null : implode(PHP_EOL, $tests);
    }

    private function samplePhpValue(ResourceBlueprint $resource, ResourceField $field, bool $raw): string
    {
        return match ($field->type) {
            'string', 'text' => "'Example ".Str::lower($field->label())."'",
            'integer' => $raw ? "'7'" : '7',
            'decimal' => "'12.50'",
            'boolean' => 'true',
            'date' => "'2026-01-15'",
            'belongsTo' => $field->relatedClass().'::factory()->create()->id',
            'image' => 'MediaAsset::factory()->withVariants()->create()->id',
            'richtext' => "['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Example ".Str::lower($field->label())."']]]]]",
            default => $raw
                ? "'".$field->enumValues[count($field->enumValues) - 1]."'"
                : $field->enumClass($resource->model).'::'.ResourceField::enumCase($field->enumValues[count($field->enumValues) - 1]),
        };
    }

    private function payloadExpectations(ResourceBlueprint $resource, string $variable): string
    {
        $lines = [];
        foreach ($resource->fields as $index => $field) {
            $subject = match ($field->type) {
                'date' => "{$variable}?->{$field->name}?->toDateString()",
                'richtext' => "{$variable}?->{$field->name}['content'][0]['content'][0]['text'] ?? null",
                default => "{$variable}?->{$field->column()}",
            };
            $assertion = match ($field->type) {
                'boolean' => 'toBeTrue()',
                'belongsTo', 'image' => 'toBeInt()',
                'richtext' => "toBe('Example ".Str::lower($field->label())."')",
                default => 'toBe('.$this->samplePhpValue($resource, $field, raw: false).')',
            };

            $lines[] = $index === 0
                ? "    expect({$subject})->{$assertion}"
                : "        ->and({$subject})->{$assertion}";
        }

        return implode(PHP_EOL, $lines).';';
    }

    private function testDefaultFilters(ResourceBlueprint $resource): string
    {
        [$sort, $direction] = $resource->defaultSort();
        $lines = [
            "                'search' => '',",
            "                'sort' => '{$sort}',",
            "                'direction' => '{$direction}',",
        ];

        foreach ($resource->filterFields() as $field) {
            $lines[] = "                '{$field->name}' => 'all',";
        }

        return implode(PHP_EOL, $lines);
    }

    private function editorAbilities(ResourceBlueprint $resource): string
    {
        return $resource->export
            ? "'create' => true, 'delete' => false, 'export' => false"
            : "'create' => true, 'delete' => false";
    }

    private function searchTest(ResourceBlueprint $resource): string
    {
        if ($resource->searchable === []) {
            return '';
        }

        $model = $resource->model;
        $column = $resource->searchable[0];
        $route = "admin.{$resource->kebabPlural()}.index";
        $component = "admin/{$resource->kebabPlural()}/index";

        return <<<PHP

        test('search matches the searchable columns', function () {
            \$admin = User::factory()->admin()->create();
            \$match = {$model}::factory()->create(['{$column}' => 'Needle in a haystack']);
            {$model}::factory()->create(['{$column}' => 'Something else']);

            \$this->actingAs(\$admin)->get(route('{$route}', ['search' => 'needle']))
                ->assertOk()
                ->assertInertia(fn (Assert \$inertia) => \$inertia
                    ->component('{$component}', false)
                    ->has('items', 1)
                    ->where('items.0.id', \$match->id)
                    ->where('filters.search', 'needle')
                );
        });

        PHP;
    }

    private function blankInputsTest(ResourceBlueprint $resource): string
    {
        $fields = $this->blankableFields($resource);
        if ($fields === []) {
            return '';
        }

        $model = $resource->model;
        $variable = '$'.$resource->variable();
        $blank = implode(', ', array_map(fn (ResourceField $field): string => "'{$field->column()}' => ''", $fields));
        $expectations = [];
        foreach ($fields as $index => $field) {
            $expectations[] = ($index === 0 ? "    expect({$variable}->{$field->column()})" : "        ->and({$variable}->{$field->column()})").'->toBeNull()';
        }
        $expectations = implode(PHP_EOL, $expectations).';';

        return <<<PHP

        test('blank optional number and date inputs are stored as null', function () {
            // The request normalises blank inputs itself, independent of the global middleware.
            \$this->withoutMiddleware(ConvertEmptyStringsToNull::class);
            \$editor = User::factory()->editor()->create();

            \$this->actingAs(\$editor)->post(route('admin.{$resource->kebabPlural()}.store'), {$resource->variable()}Payload([{$blank}]))
                ->assertSessionHasNoErrors();

            {$variable} = {$model}::query()->sole();
        {$expectations}
        });

        PHP;
    }

    private function filterTest(ResourceBlueprint $resource): string
    {
        $field = $resource->filterFields()[0] ?? null;
        if ($field === null) {
            return '';
        }

        $model = $resource->model;
        $route = "admin.{$resource->kebabPlural()}.index";
        $component = "admin/{$resource->kebabPlural()}/index";
        $setup = '';

        if ($field->type === 'boolean') {
            $active = "'yes'";
            $expected = "'yes'";
            $matching = "['{$field->name}' => true]";
            $other = "{$model}::factory()->create(['{$field->name}' => false]);";
        } elseif ($field->type === 'belongsTo') {
            $related = $field->relatedClass();
            $setup = PHP_EOL."    \$related = {$related}::factory()->create();";
            $active = '(string) $related->id';
            $expected = '(string) $related->id';
            $matching = "['{$field->column()}' => \$related->id]";
            $other = "{$model}::factory()->create();";
        } else {
            $enum = $field->enumClass($model);
            $active = "'{$field->enumValues[0]}'";
            $expected = $active;
            $matching = "['{$field->name}' => {$enum}::".ResourceField::enumCase($field->enumValues[0]).']';
            $other = count($field->enumValues) > 1
                ? "{$model}::factory()->create(['{$field->name}' => {$enum}::".ResourceField::enumCase($field->enumValues[1]).']);'
                : '';
        }

        $otherLine = $other === '' ? '' : PHP_EOL.'    '.$other;

        return <<<PHP

        test('the {$field->name} filter narrows the list and rejects unknown values', function () {
            \$admin = User::factory()->admin()->create();{$setup}
            \$match = {$model}::factory()->create({$matching});{$otherLine}

            \$this->actingAs(\$admin)->get(route('{$route}', ['{$field->name}' => {$active}]))
                ->assertOk()
                ->assertInertia(fn (Assert \$inertia) => \$inertia
                    ->component('{$component}', false)
                    ->has('items', 1)
                    ->where('items.0.id', \$match->id)
                    ->where('filters.{$field->name}', {$expected})
                );

            \$this->actingAs(\$admin)
                ->from(route('{$route}'))
                ->get(route('{$route}', ['{$field->name}' => 'not_a_value']))
                ->assertSessionHasErrors(['{$field->name}']);
        });

        PHP;
    }

    /**
     * Rejected references and documents leave the record unchanged.
     */
    private function referenceTests(ResourceBlueprint $resource): string
    {
        $cases = [];
        foreach ($resource->fields as $field) {
            $value = match ($field->type) {
                'belongsTo' => '999999',
                'image' => 'MediaAsset::factory()->create()->id',
                'richtext' => "['type' => 'doc', 'content' => [['type' => 'iframe', 'attrs' => ['src' => 'https://example.com']]]]",
                default => null,
            };

            if ($value === null) {
                continue;
            }

            $label = match ($field->type) {
                'belongsTo' => "missing {$field->name}",
                'image' => "{$field->name} not a clean image",
                default => "{$field->name} with a disallowed node",
            };

            $cases[] = "    '{$label}' => ['{$field->column()}', fn () => {$value}],";
        }

        if ($cases === []) {
            return '';
        }

        $model = $resource->model;
        $variable = '$'.$resource->variable();
        $kebab = $resource->kebabPlural();
        $cases = implode(PHP_EOL, $cases);

        return <<<PHP

        test('invalid references and documents are rejected without changing the record', function (string \$field, Closure \$value) {
            \$admin = User::factory()->admin()->create();
            {$variable} = {$model}::factory()->create();
            \$original = {$variable}->fresh()?->toArray();

            \$this->actingAs(\$admin)
                ->from(route('admin.{$kebab}.edit', {$variable}))
                ->put(route('admin.{$kebab}.update', {$variable}), {$resource->variable()}Payload([
                    \$field => \$value(),
                    'updated_at' => {$variable}->updated_at?->toIso8601String(),
                ]))
                ->assertSessionHasErrors([\$field]);

            \$this->actingAs(\$admin)->post(route('admin.{$kebab}.store'), {$resource->variable()}Payload([\$field => \$value()]))
                ->assertSessionHasErrors([\$field]);

            expect({$variable}->fresh()?->toArray())->toBe(\$original)
                ->and({$model}::query()->count())->toBe(1);
        })->with([
        {$cases}
        ]);

        PHP;
    }

    private function forbiddenExport(ResourceBlueprint $resource): ?string
    {
        if (! $resource->export) {
            return null;
        }

        return "    \$this->actingAs(\$user)->get(route('admin.{$resource->kebabPlural()}.export'))->assertForbidden();";
    }

    private function exportTests(ResourceBlueprint $resource): ?string
    {
        if (! $resource->export) {
            return null;
        }

        $model = $resource->model;
        $variable = '$'.$resource->variable();
        $kebab = $resource->kebabPlural();
        $keys = 'admin.'.$resource->camelPlural();
        $pluralLower = Str::lower($resource->pluralLabel());

        $headers = ["__('{$keys}.columnId')"];
        $matchAttributes = [];
        $otherAttributes = [];
        $query = [];
        $cellExpectations = [];
        $setup = [];
        $column = 1;

        $textField = null;
        foreach ($resource->fields as $field) {
            if (in_array($field->type, ['string', 'text'], true)) {
                $textField = $field;

                break;
            }
        }

        $filter = $resource->filterFields()[0] ?? null;

        foreach ($resource->fields as $field) {
            if (! $field->isExported()) {
                continue;
            }

            $headers[] = "__('{$keys}.fields.{$field->name}')";

            if ($field === $textField) {
                $matchAttributes[] = "'{$field->name}' => '=HYPERLINK(\"https://example.com\")'";
                $cellExpectations[] = "        ->and(\$rows[1][{$column}])->toBe('\\'=HYPERLINK(\"https://example.com\")')";
            }

            if ($field->type === 'enum') {
                $value = $field->enumValues[0];
                $matchAttributes[] = "'{$field->name}' => '{$value}'";
                $cellExpectations[] = "        ->and(\$rows[1][{$column}])->toBe(__('{$keys}.options.{$field->name}.{$value}'))";
            }

            if ($field->type === 'belongsTo') {
                $setup[] = "    \${$field->relation()} = {$field->relatedClass()}::factory()->create(['{$field->relatedLabel}' => 'Related label']);";
                $matchAttributes[] = "'{$field->column()}' => \${$field->relation()}->id";
                $cellExpectations[] = "        ->and(\$rows[1][{$column}])->toBe('Related label')";
            }

            $column++;
        }

        $headers[] = "__('{$keys}.columnCreatedAt')";
        $headers[] = "__('{$keys}.columnUpdatedAt')";

        $filterValue = null;
        if ($filter !== null) {
            if ($filter->type === 'boolean') {
                $matchAttributes[] = "'{$filter->name}' => true";
                $otherAttributes[] = "'{$filter->name}' => false";
                $filterValue = "'yes'";
            } elseif ($filter->type === 'belongsTo') {
                $filterValue = "(string) \${$filter->relation()}->id";
            } else {
                $otherValue = $filter->enumValues[1] ?? null;
                if (! in_array("'{$filter->name}' => '{$filter->enumValues[0]}'", $matchAttributes, true)) {
                    $matchAttributes[] = "'{$filter->name}' => '{$filter->enumValues[0]}'";
                }
                if ($otherValue !== null) {
                    $otherAttributes[] = "'{$filter->name}' => '{$otherValue}'";
                }
                $filterValue = "'{$filter->enumValues[0]}'";
            }

            $query[] = "'{$filter->name}' => {$filterValue}";
        }

        $hasOther = $filter !== null && ($filter->type !== 'enum' || count($filter->enumValues) > 1);
        $otherLine = $hasOther ? PHP_EOL."    {$model}::factory()->create([".implode(', ', $otherAttributes).']);' : '';
        $setupLines = $setup === [] ? '' : implode(PHP_EOL, $setup).PHP_EOL;
        $headerList = implode(', ', $headers);
        $matchList = implode(', ', $matchAttributes);
        $queryList = implode(', ', $query);
        $cells = $cellExpectations === [] ? ';' : PHP_EOL.implode(PHP_EOL, $cellExpectations).';';
        $filtersAudit = $filter === null ? '' : PHP_EOL."        ->and(\$audit->changes['filters']['new']['{$filter->name}'] ?? null)->toBe({$filterValue})";

        return <<<PHP

        /**
         * Rows of a CSV export without the UTF-8 byte order mark.
         *
         * @return list<list<string|null>>
         */
        function {$resource->variable()}CsvRows(string \$csv): array
        {
            \$handle = fopen('php://memory', 'r+');
            fwrite(\$handle, substr(\$csv, 3));
            rewind(\$handle);

            \$rows = [];
            while ((\$row = fgetcsv(\$handle, escape: '')) !== false) {
                \$rows[] = \$row;
            }
            fclose(\$handle);

            return \$rows;
        }

        test('an admin exports the filtered list as CSV with labels and safe cells', function () {
            \$admin = User::factory()->admin()->create();
        {$setupLines}    \$match = {$model}::factory()->create([{$matchList}]);{$otherLine}

            \$response = \$this->actingAs(\$admin)->get(route('admin.{$kebab}.export', [{$queryList}]));

            \$response->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8')
                ->assertDownload();

            \$csv = \$response->streamedContent();
            \$rows = {$resource->variable()}CsvRows(\$csv);

            expect(substr(\$csv, 0, 3))->toBe("\\u{FEFF}")
                ->and(\$rows)->toHaveCount(2)
                ->and(\$rows[0])->toBe([{$headerList}])
                ->and(\$rows[1][0])->toBe((string) \$match->id){$cells}

            \$audit = AuditLog::query()->where('action', AuditAction::ResourceExported)->sole();
            expect(\$audit->actor_id)->toBe(\$admin->id)
                ->and(\$audit->changes['module']['new'] ?? null)->toBe('{$kebab}')
                ->and(\$audit->changes['rows']['new'] ?? null)->toBe(1){$filtersAudit};
        });

        test('an export above the row limit is refused without a file or an audit entry', function () {
            config(['exports.{$kebab}.max_rows' => 1]);
            \$admin = User::factory()->admin()->create();
            {$model}::factory()->count(2)->create();

            \$this->actingAs(\$admin)->get(route('admin.{$kebab}.export'))
                ->assertStatus(422)
                ->assertSeeText(__('{$keys}.exportTooLarge', ['max' => 1]));

            expect(AuditLog::query()->where('action', AuditAction::ResourceExported)->exists())->toBeFalse();
        });

        test('an editor cannot export {$pluralLower}', function () {
            \$editor = User::factory()->editor()->create();
            {$model}::factory()->create();

            \$this->actingAs(\$editor)->get(route('admin.{$kebab}.export'))->assertForbidden();

            expect(AuditLog::query()->where('action', AuditAction::ResourceExported)->exists())->toBeFalse();
        });
        PHP;
    }

    private function invalidValue(ResourceField $field): string
    {
        return match ($field->type) {
            'string' => "str_repeat('a', 256)",
            'text' => "str_repeat('a', 20001)",
            'integer' => "'not-a-number'",
            'decimal' => "'1.234'",
            'boolean' => "'maybe'",
            'date' => "'15.01.2026'",
            'belongsTo', 'image' => "'not-an-id'",
            'richtext' => "'not a document'",
            default => "'not_a_value'",
        };
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => "'{$value}'", $values));
    }
}
