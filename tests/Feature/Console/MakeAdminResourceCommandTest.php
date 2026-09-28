<?php

use App\Support\ResourceGenerator\PlannedChange;
use App\Support\ResourceGenerator\ResourceBlueprint;
use App\Support\ResourceGenerator\ResourceField;
use App\Support\ResourceGenerator\ResourceGenerator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Every test generates into a throw-away copy of the files the generator
 * extends, so the repository is never touched.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->sandbox = sys_get_temp_dir().'/resource-generator-'.Str::random(12);

    $this->files->makeDirectory($this->sandbox.'/routes', 0755, true);
    $this->files->copy(base_path('routes/admin.php'), $this->sandbox.'/routes/admin.php');

    foreach (ResourceGenerator::LOCALES as $locale) {
        $this->files->makeDirectory($this->sandbox."/lang/{$locale}", 0755, true);
        $this->files->copy(lang_path("{$locale}/admin.php"), $this->sandbox."/lang/{$locale}/admin.php");
    }

    $this->generator = new ResourceGenerator($this->files, $this->sandbox, base_path('stubs/resource'));
    $this->app->instance(ResourceGenerator::class, $this->generator);

    $this->productArguments = [
        'name' => 'Product',
        '--fields' => 'name:string:required,description:text,price:decimal,stock:integer,active:boolean,launched_on:date,status:enum(draft|published)',
        '--searchable' => 'name',
        '--sortable' => 'name,price,created_at',
        '--filters' => 'status,active',
        '--no-format' => true,
    ];
});

afterEach(function () {
    $this->files->deleteDirectory($this->sandbox);
});

/**
 * Snapshot of every file under the sandbox: relative path => contents hash.
 *
 * @return array<string, string>
 */
function sandboxSnapshot(Filesystem $files, string $sandbox): array
{
    $snapshot = [];
    foreach ($files->allFiles($sandbox) as $file) {
        $snapshot[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
    }
    ksort($snapshot);

    return $snapshot;
}

test('dry run lists the planned files and writes nothing', function () {
    $before = sandboxSnapshot($this->files, $this->sandbox);

    $this->artisan('app:make-resource', [...$this->productArguments, '--dry-run' => true])
        ->expectsOutputToContain('Dry run: nothing will be written.')
        ->expectsOutputToContain('create app/Http/Controllers/Admin/Products/ProductController.php')
        ->expectsOutputToContain('create resources/js/pages/admin/products/index.tsx')
        ->expectsOutputToContain('update routes/admin.php')
        ->expectsOutputToContain('update lang/pl/admin.php')
        ->assertSuccessful();

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before);
});

test('the make:admin-resource alias runs the same command', function () {
    $this->artisan('make:admin-resource', [...$this->productArguments, '--dry-run' => true])
        ->expectsOutputToContain('create app/Models/Product.php')
        ->assertSuccessful();
});

test('generation writes plain files and extends routes and catalogs', function () {
    $this->artisan('app:make-resource', $this->productArguments)
        ->expectsOutputToContain('Product resource generated.')
        ->expectsOutputToContain('Next steps:')
        ->expectsOutputToContain('call ProductSeeder from database/seeders/DatabaseSeeder.php (it is not registered automatically)')
        ->assertSuccessful();

    foreach ([
        'app/Models/Product.php',
        'app/Enums/ProductStatus.php',
        'app/Policies/ProductPolicy.php',
        'app/Actions/Products/UpdateProduct.php',
        'app/Http/Controllers/Admin/Products/ProductController.php',
        'app/Http/Requests/Admin/Products/ListProductsRequest.php',
        'app/Http/Requests/Admin/Products/StoreProductRequest.php',
        'app/Http/Requests/Admin/Products/UpdateProductRequest.php',
        'app/Data/Admin/Products/ProductListItemData.php',
        'app/Data/Admin/Products/ProductFormData.php',
        'app/Data/Admin/Products/ProductIndexData.php',
        'app/Data/Admin/Products/ProductEditorData.php',
        'database/factories/ProductFactory.php',
        'database/seeders/ProductSeeder.php',
        'resources/js/pages/admin/products/index.tsx',
        'resources/js/pages/admin/products/form.tsx',
        'resources/js/pages/admin/products/create.tsx',
        'resources/js/pages/admin/products/edit.tsx',
        'resources/js/pages/admin/products/index.accessibility.test.tsx',
        'tests/Feature/Admin/ProductCrudTest.php',
    ] as $path) {
        expect($this->files->exists("{$this->sandbox}/{$path}"))->toBeTrue("{$path} was not generated");
    }

    expect($this->files->glob("{$this->sandbox}/database/migrations/*_create_products_table.php"))->toHaveCount(1);

    foreach ($this->files->allFiles($this->sandbox) as $file) {
        $contents = $file->getContents();
        expect($contents)->not->toContain('{{ ', "{$file->getRelativePathname()} has an unreplaced placeholder");

        if ($file->getExtension() === 'php') {
            expect(fn () => token_get_all($contents, TOKEN_PARSE))->not->toThrow(ParseError::class);
        }
    }

    expect($this->files->exists("{$this->sandbox}/database/seeders/DatabaseSeeder.php"))->toBeFalse()
        ->and($this->files->get("{$this->sandbox}/database/seeders/ProductSeeder.php"))->toContain('Product::factory()->count(10)->create();');

    $routes = $this->files->get("{$this->sandbox}/routes/admin.php");
    expect($routes)->toContain('use App\Http\Controllers\Admin\Products\ProductController;')
        ->toContain('use App\Models\Product;')
        ->toContain("->name('products.destroy')")
        ->toContain("->can('delete', 'product');");

    foreach (ResourceGenerator::LOCALES as $locale) {
        $catalog = require "{$this->sandbox}/lang/{$locale}/admin.php";
        $original = require lang_path("{$locale}/admin.php");

        expect($catalog['products']['fields']['launched_on'])->toBe('Launched on')
            ->and($catalog['products']['options']['status']['published'])->toBe('Published')
            ->and(array_diff_key($catalog, ['products' => true]))->toBe($original);
    }
});

test('an existing target file blocks the whole generation', function () {
    $this->files->makeDirectory("{$this->sandbox}/app/Policies", 0755, true);
    $this->files->put("{$this->sandbox}/app/Policies/ProductPolicy.php", '<?php // hand written');
    $before = sandboxSnapshot($this->files, $this->sandbox);

    $this->artisan('app:make-resource', $this->productArguments)
        ->expectsOutputToContain('Nothing was written.')
        ->expectsOutputToContain('app/Policies/ProductPolicy.php already exists.')
        ->assertFailed();

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before);
});

test('an existing route, catalog key or migration blocks the whole generation', function (Closure $prepare, string $message) {
    $prepare($this->files, $this->sandbox);
    $before = sandboxSnapshot($this->files, $this->sandbox);

    $this->artisan('app:make-resource', $this->productArguments)
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before);
})->with([
    'route name' => [
        function (Filesystem $files, string $sandbox): void {
            $path = "{$sandbox}/routes/admin.php";
            $files->put($path, str_replace("->name('pages.index')", "->name('products.index')", $files->get($path)));
        },
        'routes/admin.php already defines products routes.',
    ],
    'catalog key' => [
        function (Filesystem $files, string $sandbox): void {
            $path = "{$sandbox}/lang/de/admin.php";
            $files->put($path, str_replace("    'users' => [", "    'products' => [],\n    'users' => [", $files->get($path)));
        },
        'lang/de/admin.php already contains the [products] key.',
    ],
    'migration' => [
        function (Filesystem $files, string $sandbox): void {
            $files->makeDirectory("{$sandbox}/database/migrations", 0755, true);
            $files->put("{$sandbox}/database/migrations/2020_01_01_000000_create_products_table.php", '<?php');
        },
        'A migration creating the [products] table already exists',
    ],
]);

test('a failing write rolls back every file already written', function () {
    $resource = ResourceBlueprint::parse('Product', 'name:string:required');
    $plan = $this->generator->plan($resource, '2026_01_01_000000');
    $before = sandboxSnapshot($this->files, $this->sandbox);

    // The catalogs are written last; a concurrent edit makes the German one fail.
    $germanCatalog = "{$this->sandbox}/lang/de/admin.php";
    $this->files->append($germanCatalog, '// edited meanwhile');
    $before['lang/de/admin.php'] = hash_file('sha256', $germanCatalog);

    expect(fn () => $this->generator->write($plan))
        ->toThrow(RuntimeException::class, 'lang/de/admin.php changed while writing');

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before)
        ->and($this->files->isDirectory("{$this->sandbox}/app"))->toBeFalse()
        ->and(collect($plan->changes)->filter(fn (PlannedChange $change) => $change->isNew())->count())->toBeGreaterThan(20);
});

test('invalid names and field definitions are rejected before planning', function (array $arguments, string $message) {
    $before = sandboxSnapshot($this->files, $this->sandbox);

    $this->artisan('app:make-resource', [...$this->productArguments, ...$arguments])
        ->expectsOutputToContain($message)
        ->assertExitCode(2);

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before);
})->with([
    'lower case name' => [['name' => 'product'], 'must be a StudlyCase singular class name'],
    'plural name' => [['name' => 'Products'], 'must be singular'],
    'existing starter model' => [['name' => 'Page'], 'Resource name [Page] is reserved.'],
    'language keyword' => [['name' => 'Class'], 'Resource name [Class] is reserved.'],
    'missing fields' => [['--fields' => ''], 'At least one field is required'],
    'reserved column' => [['--fields' => 'id:integer', '--searchable' => '', '--sortable' => '', '--filters' => ''], 'Field name [id] is reserved.'],
    'camel case field' => [['--fields' => 'fullName:string', '--searchable' => '', '--sortable' => '', '--filters' => ''], 'must be a snake_case identifier'],
    'unknown type' => [['--fields' => 'name:json'], 'must look like name:type[:required]'],
    'duplicate field' => [['--fields' => 'name:string,name:text', '--sortable' => '', '--filters' => ''], 'Field [name] is defined more than once.'],
    'empty enum' => [['--fields' => 'status:enum()', '--searchable' => '', '--sortable' => '', '--filters' => ''], 'needs at least one value'],
    'reserved enum value' => [['--fields' => 'status:enum(all|draft)', '--searchable' => '', '--sortable' => '', '--filters' => ''], 'Enum value [all] of field [status] is reserved'],
    'search on a number' => [['--searchable' => 'price'], 'Searchable column [price] must be a string or text field.'],
    'sort on long text' => [['--sortable' => 'description'], 'Sortable column [description] must be a non-text field'],
    'filter on a string' => [['--filters' => 'name'], 'Filter [name] must be a boolean or enum field.'],
]);

test('number and date fields render native inputs, validate strictly and treat blank optional values as null', function () {
    $this->artisan('app:make-resource', $this->productArguments)->assertSuccessful();

    $form = $this->files->get("{$this->sandbox}/resources/js/pages/admin/products/form.tsx");
    $compact = preg_replace('/\s+/', ' ', $form);

    expect($form)->toContain('    stock: string;')
        ->toContain('    price: string;')
        ->toContain('    launched_on: string;')
        ->toContain("stock: record.stock === null ? '' : String(record.stock),")
        ->toContain("launched_on: record.launchedOn ?? '',")
        ->not->toContain('dateHint')
        ->and($compact)->toContain("type: 'number', name: 'stock', label: t('admin.products.fields.stock'), step: 1, }")
        ->toContain("type: 'number', name: 'price', label: t('admin.products.fields.price'), step: 0.01, }")
        ->toContain("type: 'date', name: 'launched_on', label: t('admin.products.fields.launched_on'), }")
        ->toContain("type: 'text', name: 'name',");

    $store = $this->files->get("{$this->sandbox}/app/Http/Requests/Admin/Products/StoreProductRequest.php");
    expect($store)->toContain("'stock' => ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'],")
        ->toContain("'price' => ['nullable', 'numeric', 'decimal:0,2', 'min:-9999999999.99', 'max:9999999999.99'],")
        ->toContain("'launched_on' => ['nullable', 'date_format:Y-m-d'],")
        ->toContain('$this->merge(self::blankOptionalInputsAsNull($this->all()));')
        ->toContain("foreach (['price', 'stock', 'launched_on'] as \$name)");

    $update = $this->files->get("{$this->sandbox}/app/Http/Requests/Admin/Products/UpdateProductRequest.php");
    expect($update)->toContain('$this->merge(StoreProductRequest::blankOptionalInputsAsNull($this->all()));');

    $model = $this->files->get("{$this->sandbox}/app/Models/Product.php");
    expect($model)->toContain("'stock' => 'integer',")
        ->toContain("'price' => 'decimal:2',")
        ->toContain("'launched_on' => 'date',");

    $test = $this->files->get("{$this->sandbox}/tests/Feature/Admin/ProductCrudTest.php");
    expect($test)->toContain("'stock' => '7',")
        ->toContain("test('blank optional number and date inputs are stored as null'")
        ->toContain('$this->withoutMiddleware(ConvertEmptyStringsToNull::class);');
});

test('resources without optional number or date fields get no input normalisation', function () {
    $this->artisan('app:make-resource', [
        'name' => 'Product',
        '--fields' => 'name:string:required,stock:integer:required',
        '--no-format' => true,
    ])->assertSuccessful();

    $store = $this->files->get("{$this->sandbox}/app/Http/Requests/Admin/Products/StoreProductRequest.php");
    $test = $this->files->get("{$this->sandbox}/tests/Feature/Admin/ProductCrudTest.php");

    expect($store)->not->toContain('prepareForValidation')
        ->not->toContain('blankOptionalInputsAsNull')
        ->and($this->files->get("{$this->sandbox}/app/Http/Requests/Admin/Products/UpdateProductRequest.php"))->not->toContain('prepareForValidation')
        ->and($test)->not->toContain('blank optional number and date inputs')
        ->and($this->files->get("{$this->sandbox}/resources/js/pages/admin/products/form.tsx"))->toContain("type: 'number',");
});

/**
 * Copy an existing model into the sandbox so belongsTo targets resolve.
 */
function copyModelIntoSandbox(Filesystem $files, string $sandbox, string $model): void
{
    $files->ensureDirectoryExists("{$sandbox}/app/Models");
    $files->copy(app_path("Models/{$model}.php"), "{$sandbox}/app/Models/{$model}.php");
}

dataset('full feature arguments', [[[
    'name' => 'Product',
    '--fields' => 'name:string:required,faq:belongsTo(Faq.question):required,cover:image,body:richtext,status:enum(draft|published:success|archived:danger),active:boolean',
    '--searchable' => 'name',
    '--sortable' => 'name,created_at',
    '--filters' => 'status,faq',
    '--export' => true,
    '--no-format' => true,
]]]);

test('relation, image, rich text, badge tones and export generate the expected fragments', function (array $arguments) {
    copyModelIntoSandbox($this->files, $this->sandbox, 'Faq');

    $this->artisan('app:make-resource', $arguments)->assertSuccessful();

    foreach ($this->files->allFiles($this->sandbox) as $file) {
        expect($file->getContents())->not->toContain('{{ ', "{$file->getRelativePathname()} has an unreplaced placeholder");

        if ($file->getExtension() === 'php') {
            expect(fn () => token_get_all($file->getContents(), TOKEN_PARSE))->not->toThrow(ParseError::class);
        }
    }

    $read = fn (string $path): string => $this->files->get("{$this->sandbox}/{$path}");
    $migration = $this->files->get($this->files->glob("{$this->sandbox}/database/migrations/*_create_products_table.php")[0]);

    expect($migration)->toContain("\$table->foreignId('faq_id')->index()->constrained('faqs')->restrictOnDelete();")
        ->toContain("\$table->foreignId('cover_media_id')->nullable()->index()->constrained('media_assets')->nullOnDelete();")
        ->toContain("\$table->json('body')->nullable();");

    expect($read('app/Models/Product.php'))->toContain("#[Fillable(['name', 'faq_id', 'cover_media_id', 'body', 'status', 'active'])]")
        ->toContain('public function faq(): BelongsTo')
        ->toContain("return \$this->belongsTo(Faq::class, 'faq_id');")
        ->toContain("return \$this->belongsTo(MediaAsset::class, 'cover_media_id');")
        ->toContain("'body' => 'array',");

    expect($read('database/factories/ProductFactory.php'))->toContain("'faq_id' => Faq::factory(),")
        ->toContain('use App\Models\Faq;');

    expect($read('app/Http/Requests/Admin/Products/StoreProductRequest.php'))
        ->toContain("'faq_id' => ['required', 'integer', Rule::exists(Faq::class, 'id')],")
        ->toContain("'cover_media_id' => ['nullable', 'integer', new DamImage],")
        ->toContain("'body' => ['nullable', 'array', new RichTextDocument(app(RichTextRenderer::class))],")
        ->toContain('return self::sanitizeRichText($this->validated());')
        ->toContain('$renderer->sanitize($values[$name])');

    expect($read('app/Http/Requests/Admin/Products/UpdateProductRequest.php'))
        ->toContain("return StoreProductRequest::sanitizeRichText(\$this->safe()->except(['updated_at']));");

    expect($read('app/Data/Admin/Products/ProductFormData.php'))
        ->toContain("#[LiteralTypeScriptType('{ [key: string]: unknown } | null')]")
        ->toContain('public ?array $body,')
        ->toContain('public ?int $faqId,');

    expect($read('app/Data/Admin/Products/ProductListItemData.php'))
        ->toContain('public ?string $faqLabel,')
        ->toContain('faqLabel: (string) $product->faq->question,')
        ->not->toContain('$body');

    expect($read('app/Data/Admin/Products/ProductEditorData.php'))
        ->toContain('public array $faqOptions,')
        ->toContain('public bool $faqOptionsTruncated,');

    expect($read('app/Http/Requests/Admin/Products/ListProductsRequest.php'))
        ->toContain("->filter('faq', ['all', ...\$this->faqFilterValues()]")
        ->toContain("\$query->where('faq_id', (int) \$value);");

    expect($read('app/Http/Controllers/Admin/Products/ProductController.php'))
        ->toContain("Product::query()->with(['faq:id,question'])")
        ->toContain('public const int EXPORT_MAX_ROWS = 10_000;')
        ->toContain('public function export(ListProductsRequest $request, RecordAuditEvent $recordAuditEvent): HttpResponse|StreamedResponse')
        ->toContain('(clone $query)->reorder()->count()')
        ->toContain('AuditAction::ResourceExported')
        ->toContain("RecordAuditEvent::change(null, Arr::except(\$filters, ['search']))")
        ->toContain('fwrite($output, "\u{FEFF}");')
        ->toContain('CsvCell::safe($product->faq->question),')
        ->toContain("ProductStatus::Published => __('admin.products.options.status.published'),")
        ->toContain('->limit(RecordOptionData::LIMIT + 1)')
        ->not->toContain('CsvCell::safe($product->body)')
        ->not->toContain('CsvCell::safe($product->cover_media_id)');

    expect($read('app/Policies/ProductPolicy.php'))->toContain('public function export(User $user): bool');

    expect($read('routes/admin.php'))->toContain("Route::get('/products/export', [ProductController::class, 'export'])")
        ->toContain("->name('products.export')")
        ->toContain("->can('export', Product::class)")
        ->toContain("->middleware('throttle:6,1');");

    expect($read('resources/js/pages/admin/products/index.tsx'))
        ->toContain("published: 'success',")
        ->toContain("archived: 'danger',")
        ->toContain("draft: 'neutral',")
        ->toContain('<Badge tone={statusTones[row.status]}>')
        ->toContain('exportMethod({ query: { ...filters } })')
        ->toContain('download')
        ->toContain("row.faqLabel ?? '—'")
        ->toContain('...faqOptions.map((option) => ({');

    expect($read('resources/js/pages/admin/products/form.tsx'))
        ->toContain("type: 'richText',")
        ->toContain("type: 'image',")
        ->toContain("name: 'faq_id',")
        ->toContain('useMediaImagePicker')
        ->toContain('documentPayload(data.body)')
        ->toContain("t('admin.richText.toolbar')");

    expect($read('tests/Feature/Admin/ProductCrudTest.php'))
        ->toContain("test('invalid references and documents are rejected without changing the record'")
        ->toContain("test('an export above the row limit is refused without a file or an audit entry'")
        ->toContain('=HYPERLINK')
        ->toContain("\$this->actingAs(\$user)->get(route('admin.products.export'))->assertForbidden();");

    $keys = [];
    foreach (ResourceGenerator::LOCALES as $locale) {
        $catalog = require "{$this->sandbox}/lang/{$locale}/admin.php";
        $keys[$locale] = array_keys(Arr::dot($catalog['products']));

        expect($catalog['products']['exportTooLarge'])->toContain(':max')
            ->and($catalog['products']['exportFilename'])->toBe('products-:date.csv')
            ->and($catalog['products'])->toHaveKeys(['export', 'faqPlaceholder', 'faqEmpty', 'noneOption', 'optionsTruncated', 'imageChoose'])
            ->and($catalog['products']['fields'])->toHaveKeys(['faq', 'cover', 'body']);
    }

    expect($keys['pl'])->toBe($keys['en'])->and($keys['de'])->toBe($keys['en']);
})->with('full feature arguments');

test('a dry run with the export writes nothing', function (array $arguments) {
    copyModelIntoSandbox($this->files, $this->sandbox, 'Faq');
    $before = sandboxSnapshot($this->files, $this->sandbox);

    $this->artisan('app:make-resource', [...$arguments, '--dry-run' => true])
        ->expectsOutputToContain('Dry run: nothing will be written.')
        ->expectsOutputToContain('update routes/admin.php')
        ->assertSuccessful();

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before);
})->with('full feature arguments');

test('an existing export route blocks the whole generation', function (array $arguments) {
    copyModelIntoSandbox($this->files, $this->sandbox, 'Faq');
    $path = "{$this->sandbox}/routes/admin.php";
    $this->files->put($path, str_replace("->name('pages.index')", "->name('products.export')", $this->files->get($path)));
    $before = sandboxSnapshot($this->files, $this->sandbox);

    $this->artisan('app:make-resource', $arguments)
        ->expectsOutputToContain('routes/admin.php already defines products routes.')
        ->assertFailed();

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before);
})->with('full feature arguments');

test('relations, rich text and badge tones are validated before planning', function (array $arguments, string $message) {
    copyModelIntoSandbox($this->files, $this->sandbox, 'Faq');
    $before = sandboxSnapshot($this->files, $this->sandbox);

    $this->artisan('app:make-resource', [
        'name' => 'Product',
        '--searchable' => '',
        '--sortable' => '',
        '--filters' => '',
        '--no-format' => true,
        ...$arguments,
    ])
        ->expectsOutputToContain($message)
        ->assertExitCode(2);

    expect(sandboxSnapshot($this->files, $this->sandbox))->toBe($before);
})->with([
    'missing related model' => [['--fields' => 'name:string,category:belongsTo(Category.name)'], 'model app/Models/Category.php does not exist.'],
    'user as related model' => [['--fields' => 'name:string,owner:belongsTo(User.name)'], 'cannot reference [User]'],
    'unknown label column' => [['--fields' => 'name:string,faq:belongsTo(Faq.nope)'], 'column [nope] is not a fillable or documented attribute of App\Models\Faq.'],
    'malformed relation' => [['--fields' => 'name:string,faq:belongsTo(faq)'], 'must reference its model as belongsTo(Model.label_column)'],
    'relation named like a column' => [['--fields' => 'name:string,faq_id:belongsTo(Faq.question)'], 'must name the relation'],
    'required image' => [['--fields' => 'name:string,cover:image:required'], 'Field [cover] of type image is always optional'],
    'rich text searchable' => [['--fields' => 'name:string,body:richtext', '--searchable' => 'body'], 'Searchable column [body] must be a string or text field.'],
    'rich text sortable' => [['--fields' => 'name:string,body:richtext', '--sortable' => 'body'], 'Sortable column [body] must be a non-text field'],
    'relation sortable' => [['--fields' => 'name:string,faq:belongsTo(Faq.question)', '--sortable' => 'faq'], 'Sortable column [faq] must be a non-text field'],
    'image filter' => [['--fields' => 'name:string,cover:image', '--filters' => 'cover'], 'Filter [cover] must be a boolean or enum field'],
    'unknown badge tone' => [['--fields' => 'status:enum(draft|published:rainbow)'], 'Enum tone [rainbow] of value [published] of field [status] must be one of: neutral, primary, success, danger, outline.'],
]);

test('enum badge tones are parsed per value and default to neutral', function () {
    $status = ResourceBlueprint::parse('Product', 'status:enum(draft|published:success|archived:danger)')->field('status');

    expect($status?->enumValues)->toBe(['draft', 'published', 'archived'])
        ->and($status?->enumTones)->toBe(['published' => 'success', 'archived' => 'danger'])
        ->and($status?->toneOf('draft'))->toBe('neutral');
});

test('allowed badge tones match the Badge primitive', function () {
    $badge = (string) file_get_contents(resource_path('js/design-system/primitives/badge.tsx'));
    preg_match('/type BadgeTone = ([^;]+);/', $badge, $matches);
    preg_match_all("/'([a-z]+)'/", $matches[1] ?? '', $tones);

    expect($tones[1])->toBe(ResourceField::BADGE_TONES);
});

test('the reserved names cover the starter models and the navigation modules', function (string $name) {
    expect(fn () => ResourceBlueprint::parse($name, 'name:string'))
        ->toThrow(InvalidArgumentException::class, "Resource name [{$name}] is reserved.");
})->with(['Menu', 'MenuItem', 'Navigation', 'HomeSection', 'SiteSetting', 'Article', 'AuditLog', 'ContactMessage', 'Faq']);
