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
            ]),
            "app/Models/{$model}.php" => $this->php('model', $resource, [
                'imports' => $this->enumImports($resource),
                'propertyDocs' => $this->propertyDocs($resource),
                'fillable' => implode(', ', array_map(fn (ResourceField $field): string => "'{$field->name}'", $resource->fields)),
                'casts' => $this->casts($resource),
            ]),
            "database/factories/{$model}Factory.php" => $this->php('factory', $resource, [
                'imports' => $this->enumImports($resource),
                'definition' => $this->factoryDefinition($resource),
            ]),
            "app/Policies/{$model}Policy.php" => $this->php('policy', $resource),
            "app/Actions/{$plural}/Update{$model}.php" => $this->php('action.update', $resource),
            "app/Http/Controllers/Admin/{$plural}/{$model}Controller.php" => $this->php('controller', $resource),
            "app/Http/Requests/Admin/{$plural}/List{$plural}Request.php" => $this->php('request.list', $resource, [
                'imports' => $resource->filters === [] ? null : 'use Illuminate\Database\Eloquent\Builder;',
                'definition' => $this->listDefinition($resource),
            ]),
            "app/Http/Requests/Admin/{$plural}/Store{$model}Request.php" => $this->php('request.store', $resource, [
                'imports' => $this->storeRequestImports($resource),
                'rules' => $this->validationRules($resource),
            ]),
            "app/Http/Requests/Admin/{$plural}/Update{$model}Request.php" => $this->php('request.update', $resource),
            "app/Data/Admin/{$plural}/{$model}ListItemData.php" => $this->php('data.list-item', $resource, [
                'imports' => $this->enumImports($resource),
                'properties' => $this->dataProperties($resource, listOnly: true),
                'assignments' => $this->dataAssignments($resource, listOnly: true),
            ]),
            "app/Data/Admin/{$plural}/{$model}FormData.php" => $this->php('data.form', $resource, [
                'imports' => $this->enumImports($resource),
                'properties' => $this->dataProperties($resource, listOnly: false),
                'blankAssignments' => $this->blankAssignments($resource),
                'assignments' => $this->dataAssignments($resource, listOnly: false),
            ]),
            "app/Data/Admin/{$plural}/{$model}ListFiltersData.php" => $this->php('data.list-filters', $resource, [
                'sortType' => implode(' | ', array_map(fn (string $column): string => "'{$column}'", $resource->sortable)),
                'filterProperties' => $this->filterProperties($resource),
            ]),
            "app/Data/Admin/{$plural}/{$model}IndexData.php" => $this->php('data.index', $resource),
            "app/Data/Admin/{$plural}/{$model}EditorData.php" => $this->php('data.editor', $resource),
            "app/Data/Admin/{$plural}/{$model}AbilitiesData.php" => $this->php('data.abilities', $resource),
            "{$pages}/index.tsx" => $this->render('react.index', $resource, [
                'primitives' => $this->indexPrimitives($resource),
                'labelMaps' => $this->labelMaps($resource),
                'rowTitle' => $this->rowTitle($resource),
                'searchableProp' => $resource->searchable === [] ? null : '                searchable',
                'filterFields' => $this->filterFields($resource),
                'columns' => $this->columns($resource),
            ]),
            "{$pages}/form.tsx" => $this->render('react.form', $resource, [
                'valueTypes' => $this->formValueTypes($resource),
                'fieldNames' => implode(', ', array_map(fn (ResourceField $field): string => "'{$field->name}'", $resource->fields)),
                'toValues' => $this->formToValues($resource),
                'formFields' => $this->formFields($resource),
            ]),
            "{$pages}/create.tsx" => $this->render('react.create', $resource),
            "{$pages}/edit.tsx" => $this->render('react.edit', $resource),
            "{$pages}/index.accessibility.test.tsx" => $this->render('react.index-test', $resource, [
                'items' => $this->testRows($resource),
                'filters' => $this->testFilters($resource),
                'firstRowTitle' => $this->testFirstRowTitle($resource),
                'filterTest' => $this->uiFilterTest($resource),
            ]),
            "tests/Feature/Admin/{$model}CrudTest.php" => $this->php('test.feature', $resource, [
                'imports' => $this->enumImports($resource),
                'payload' => $this->testPayload($resource),
                'defaultFilters' => $this->testDefaultFilters($resource),
                'searchTest' => $this->searchTest($resource),
                'sortColumn' => $resource->sortable[0],
                'filterTest' => $this->filterTest($resource),
                'createdExpectations' => $this->payloadExpectations($resource, '$'.$resource->variable()),
                'updatedExpectations' => $this->payloadExpectations($resource, '$'.$resource->variable()),
                'requiredFields' => implode(', ', array_map(
                    fn (ResourceField $field): string => "'{$field->name}'",
                    array_values(array_filter($resource->fields, fn (ResourceField $field): bool => $field->isRequired())),
                )),
                'invalidField' => $resource->fields[0]->name,
                'invalidValue' => $this->invalidValue($resource->fields[0]),
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
        return $this->render('routes', $resource);
    }

    /**
     * The `admin.{camelPlural}` catalog entry for one locale (en, pl or de).
     */
    public function langBlock(ResourceBlueprint $resource, string $locale): string
    {
        $fieldLabels = [];
        foreach ($resource->fields as $field) {
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

    private function enumImports(ResourceBlueprint $resource): ?string
    {
        $imports = array_map(
            fn (ResourceField $field): string => 'use App\\Enums\\'.$field->enumClass($resource->model).';',
            $resource->enumFields(),
        );

        return $imports === [] ? null : implode(PHP_EOL, $imports);
    }

    private function storeRequestImports(ResourceBlueprint $resource): ?string
    {
        $enums = $this->enumImports($resource);

        return $enums === null ? null : $enums.PHP_EOL.'use Illuminate\Validation\Rule;';
    }

    private function migrationColumns(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            $column = match ($field->type) {
                'string' => "\$table->string('{$field->name}')",
                'text' => "\$table->text('{$field->name}')",
                'integer' => "\$table->integer('{$field->name}')",
                'decimal' => "\$table->decimal('{$field->name}', 12, 2)",
                'boolean' => "\$table->boolean('{$field->name}')->default(false)",
                'date' => "\$table->date('{$field->name}')",
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

    private function propertyDocs(ResourceBlueprint $resource): string
    {
        $lines = [];
        foreach ($resource->fields as $field) {
            $type = match ($field->type) {
                'integer' => 'int',
                'boolean' => 'bool',
                'date' => 'CarbonImmutable',
                'enum' => $field->enumClass($resource->model),
                default => 'string',
            };

            $lines[] = " * @property {$type}".($field->isRequired() ? '' : '|null')." \${$field->name}";
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
                'enum' => $field->enumClass($resource->model).'::class',
                default => null,
            };

            if ($cast !== null) {
                $lines[] = "            '{$field->name}' => {$cast},";
            }
        }

        return $lines === [] ? '[]' : '['.PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL.'        ]';
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
                default => 'fake()->randomElement('.$field->enumClass($resource->model).'::cases())',
            };

            $lines[] = "            '{$field->name}' => {$value},";
        }

        return implode(PHP_EOL, $lines);
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
                default => "{$presence}, Rule::enum(".$field->enumClass($resource->model).'::class)',
            };

            $lines[] = "            '{$field->name}' => [{$rules}],";
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

            $type = match ($field->type) {
                'integer' => 'int',
                'boolean' => 'bool',
                'enum' => $field->enumClass($resource->model),
                default => 'string',
            };

            $nullable = in_array($field->type, ['boolean', 'enum'], true)
                ? false
                : ! $listOnly || ! $field->isRequired();

            $lines[] = '        public '.($nullable ? '?' : '')."{$type} \${$field->property()},";
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

            $value = "{$variable}->{$field->name}";
            if ($field->type === 'date') {
                $value .= ($field->isRequired() ? '->' : '?->').'toDateString()';
            }

            $lines[] = "            {$field->property()}: {$value},";
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

        return implode(PHP_EOL, $lines);
    }

    private function filterProperties(ResourceBlueprint $resource): ?string
    {
        $lines = [];
        foreach ($resource->filterFields() as $field) {
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

    private function labelMaps(ResourceBlueprint $resource): string
    {
        $blocks = [];
        foreach ($resource->enumFields() as $field) {
            $lines = ["    const {$field->property()}Labels: Record<App.Enums.{$field->enumClass($resource->model)}, string> = {"];
            foreach ($field->enumValues as $value) {
                $lines[] = "        {$value}: t('admin.{$resource->camelPlural()}.options.{$field->name}.{$value}'),";
            }
            $lines[] = '    };';
            $blocks[] = implode(PHP_EOL, $lines);
        }

        return $blocks === [] ? '' : PHP_EOL.implode(PHP_EOL.PHP_EOL, $blocks).PHP_EOL;
    }

    private function rowTitle(ResourceBlueprint $resource): string
    {
        $untitled = "t('admin.{$resource->camelPlural()}.untitled', { id: row.id })";
        $title = $resource->titleField();

        return $title === null ? $untitled : "row.{$title->property()} || {$untitled}";
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
            $columns[] = $this->column('id', "t('{$keys}.columnId')", in_array('id', $resource->sortable, true), $titleField === null ? $link : 'row.id');
        }

        foreach ($resource->fields as $field) {
            if (! $field->isListed()) {
                continue;
            }

            $property = "row.{$field->property()}";
            $render = match (true) {
                $field === $titleField => $link,
                $field->type === 'boolean' => "(<Badge tone={{$property} ? 'success' : 'neutral'}>{{$property} ? t('{$keys}.yes') : t('{$keys}.no')}</Badge>)",
                $field->type === 'enum' => "(<Badge tone=\"neutral\">{{$field->property()}Labels[{$property}]}</Badge>)",
                $field->isRequired() => $property,
                default => "{$property} ?? '—'",
            };

            $columns[] = $this->column($field->name, "t('{$keys}.fields.{$field->name}')", in_array($field->name, $resource->sortable, true), $render);
        }

        $columns[] = $this->column('created_at', "t('{$keys}.columnCreatedAt')", in_array('created_at', $resource->sortable, true), "row.createdAt ? formatDate(row.createdAt) : '—'");

        if (in_array('updated_at', $resource->sortable, true)) {
            $columns[] = $this->column('updated_at', "t('{$keys}.columnUpdatedAt')", true, "row.updatedAt ? formatDate(row.updatedAt) : '—'");
        }

        return implode(PHP_EOL, $columns);
    }

    private function column(string $key, string $label, bool $sortable, string $render): string
    {
        return implode(PHP_EOL, array_filter([
            '                    {',
            "                        key: '{$key}',",
            "                        label: {$label},",
            $sortable ? '                        sortable: true,' : null,
            "                        render: (row) => {$render},",
            '                    },',
        ]));
    }

    private function formValueTypes(ResourceBlueprint $resource): string
    {
        return implode(PHP_EOL, array_map(fn (ResourceField $field): string => "    {$field->name}: ".match ($field->type) {
            'boolean' => 'boolean',
            'enum' => 'App.Enums.'.$field->enumClass($resource->model),
            default => 'string',
        }.';', $resource->fields));
    }

    private function formToValues(ResourceBlueprint $resource): string
    {
        return implode(PHP_EOL, array_map(function (ResourceField $field): string {
            $property = "record.{$field->property()}";
            $value = match ($field->type) {
                'boolean', 'enum' => $property,
                'integer' => "{$property} === null ? '' : String({$property})",
                default => "{$property} ?? ''",
            };

            return "        {$field->name}: {$value},";
        }, $resource->fields));
    }

    private function formFields(ResourceBlueprint $resource): string
    {
        $keys = 'admin.'.$resource->camelPlural();
        $blocks = [];

        foreach ($resource->fields as $field) {
            $indent = str_repeat(' ', 32);
            $lines = ['                            {'];
            $lines[] = $indent.'type: '.match ($field->type) {
                'text' => "'textarea'",
                'boolean' => "'switch'",
                'enum' => "'select'",
                default => "'text'",
            }.',';
            $lines[] = $indent."name: '{$field->name}',";
            $lines[] = $indent."label: t('{$keys}.fields.{$field->name}'),";

            if ($field->type === 'text') {
                $lines[] = $indent.'rows: 5,';
            }

            if ($field->type === 'date') {
                $lines[] = $indent."hint: t('{$keys}.dateHint'),";
            }

            if ($field->type === 'enum') {
                $lines[] = $indent.'options: [';
                foreach ($field->enumValues as $value) {
                    $lines[] = $indent."    { value: '{$value}', label: t('{$keys}.options.{$field->name}.{$value}') },";
                }
                $lines[] = $indent.'],';
            }

            if ($field->isRequired() && $field->type !== 'boolean') {
                $lines[] = $indent.'required: true,';
            }

            $lines[] = '                            },';
            $blocks[] = implode(PHP_EOL, $lines);
        }

        return implode(PHP_EOL, $blocks);
    }

    private function testRows(ResourceBlueprint $resource): string
    {
        $rows = [];
        foreach ([7 => 0, 8 => 1] as $id => $variant) {
            $lines = ['            {', "                id: {$id},"];
            foreach ($resource->fields as $field) {
                if ($field->isListed()) {
                    $lines[] = "                {$field->property()}: {$this->sampleTsValue($field, $variant)},";
                }
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

    private function testFirstRowTitle(ResourceBlueprint $resource): string
    {
        $title = $resource->titleField();

        return $title === null
            ? "admin.{$resource->camelPlural()}.untitled"
            : 'First '.Str::lower($title->label());
    }

    private function uiFilterTest(ResourceBlueprint $resource): ?string
    {
        $field = $resource->filterFields()[0] ?? null;
        if ($field === null) {
            return null;
        }

        [$sort, $direction] = $resource->defaultSort();
        $active = $field->type === 'boolean' ? 'yes' : $field->enumValues[0];
        $keys = 'admin.'.$resource->camelPlural();

        return <<<TS

            it('clears the filters to the server defaults', async () => {
                pageProps = makeProps({ create: true, delete: true });
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
        return implode(PHP_EOL, array_map(
            fn (ResourceField $field): string => "        '{$field->name}' => {$this->samplePhpValue($resource, $field, raw: true)},",
            $resource->fields,
        ));
    }

    private function samplePhpValue(ResourceBlueprint $resource, ResourceField $field, bool $raw): string
    {
        return match ($field->type) {
            'string', 'text' => "'Example ".Str::lower($field->label())."'",
            'integer' => '7',
            'decimal' => "'12.50'",
            'boolean' => 'true',
            'date' => "'2026-01-15'",
            default => $raw
                ? "'".$field->enumValues[count($field->enumValues) - 1]."'"
                : $field->enumClass($resource->model).'::'.ResourceField::enumCase($field->enumValues[count($field->enumValues) - 1]),
        };
    }

    private function payloadExpectations(ResourceBlueprint $resource, string $variable): string
    {
        $lines = [];
        foreach ($resource->fields as $index => $field) {
            $subject = $field->type === 'date'
                ? "{$variable}?->{$field->name}?->toDateString()"
                : "{$variable}?->{$field->name}";
            $assertion = $field->type === 'boolean'
                ? 'toBeTrue()'
                : 'toBe('.$this->samplePhpValue($resource, $field, raw: false).')';

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

    private function filterTest(ResourceBlueprint $resource): string
    {
        $field = $resource->filterFields()[0] ?? null;
        if ($field === null) {
            return '';
        }

        $model = $resource->model;
        $route = "admin.{$resource->kebabPlural()}.index";
        $component = "admin/{$resource->kebabPlural()}/index";

        if ($field->type === 'boolean') {
            $active = 'yes';
            $matching = "['{$field->name}' => true]";
            $other = "{$model}::factory()->create(['{$field->name}' => false]);";
        } else {
            $enum = $field->enumClass($model);
            $active = $field->enumValues[0];
            $matching = "['{$field->name}' => {$enum}::".ResourceField::enumCase($active).']';
            $other = count($field->enumValues) > 1
                ? "{$model}::factory()->create(['{$field->name}' => {$enum}::".ResourceField::enumCase($field->enumValues[1]).']);'
                : '';
        }

        $otherLine = $other === '' ? '' : PHP_EOL.'    '.$other;

        return <<<PHP

        test('the {$field->name} filter narrows the list and rejects unknown values', function () {
            \$admin = User::factory()->admin()->create();
            \$match = {$model}::factory()->create({$matching});{$otherLine}

            \$this->actingAs(\$admin)->get(route('{$route}', ['{$field->name}' => '{$active}']))
                ->assertOk()
                ->assertInertia(fn (Assert \$inertia) => \$inertia
                    ->component('{$component}', false)
                    ->has('items', 1)
                    ->where('items.0.id', \$match->id)
                    ->where('filters.{$field->name}', '{$active}')
                );

            \$this->actingAs(\$admin)
                ->from(route('{$route}'))
                ->get(route('{$route}', ['{$field->name}' => 'not_a_value']))
                ->assertSessionHasErrors(['{$field->name}']);
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
