<?php

use App\Support\ResourceGenerator\PlannedChange;
use App\Support\ResourceGenerator\ResourceBlueprint;
use App\Support\ResourceGenerator\ResourceGenerator;
use Illuminate\Filesystem\Filesystem;
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
