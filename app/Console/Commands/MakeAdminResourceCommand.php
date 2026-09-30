<?php

namespace App\Console\Commands;

use App\Support\ResourceGenerator\PlannedChange;
use App\Support\ResourceGenerator\ResourceBlueprint;
use App\Support\ResourceGenerator\ResourceGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

#[Signature('app:make-resource
    {name : Singular StudlyCase model name, e.g. Product}
    {--fields= : Comma separated name:type[:required]; types: string, text, integer, decimal, boolean, date, enum(a|b:success), belongsTo(Model.label_column), image, richtext}
    {--searchable= : Comma separated string/text fields matched by the list search}
    {--sortable= : Comma separated sortable columns (non-text, non-relation fields, id, created_at, updated_at); default created_at}
    {--filters= : Comma separated boolean/enum/belongsTo fields offered as list filters}
    {--export : Add a CSV export of the filtered list (administrators only, audited)}
    {--dry-run : List the files that would be created or changed without writing anything}
    {--no-format : Skip Pint and the frontend formatter on the generated files}', aliases: ['make:admin-resource'])]
#[Description('Generate a plain admin CRUD resource (migration, model, factory, seeder, policy, controller, requests, Data, React pages, i18n, routes and tests)')]
class MakeAdminResourceCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ResourceGenerator $generator): int
    {
        try {
            $resource = ResourceBlueprint::parse(
                (string) $this->argument('name'),
                (string) $this->option('fields'),
                (string) $this->option('searchable'),
                (string) $this->option('sortable'),
                (string) $this->option('filters'),
                (bool) $this->option('export'),
            );
        } catch (InvalidArgumentException $exception) {
            foreach (explode(PHP_EOL, $exception->getMessage()) as $message) {
                $this->error($message);
            }

            return self::INVALID;
        }

        $relationErrors = $generator->relationErrors($resource);
        if ($relationErrors !== []) {
            foreach ($relationErrors as $message) {
                $this->error($message);
            }

            return self::INVALID;
        }

        $plan = $generator->plan($resource, now()->format('Y_m_d_His'));

        $dryRun = (bool) $this->option('dry-run');
        $this->line($dryRun ? 'Dry run: nothing will be written.' : 'Planned changes:');
        foreach ($plan->changes as $change) {
            $this->line(sprintf('  %s %s', $change->isNew() ? 'create' : 'update', $change->path));
        }

        if ($plan->conflicts !== []) {
            $this->newLine();
            $this->error('Nothing was written. Resolve these conflicts first (existing files are never overwritten):');
            foreach ($plan->conflicts as $conflict) {
                $this->line("  - {$conflict}");
            }

            return self::FAILURE;
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        try {
            $generator->write($plan);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('no-format')) {
            $this->format($generator, $plan->changes);
        }

        $this->newLine();
        $this->info("{$resource->model} resource generated.");
        $this->line('Next steps:');
        $this->line('  1. Review the generated files and the English/Polish/German texts in lang/*/admin.php.');
        $this->line("  2. make artisan ARGS='migrate --no-interaction'");
        $this->line("  3. make composer ARGS='types:generate'");
        $this->line("  4. make artisan ARGS='wayfinder:generate --with-form --no-interaction'");
        $this->line("  5. Add a navigation item to a group in resources/js/layouts/admin-layout.tsx (admin.{$resource->camelPlural()}.navLabel, route admin.{$resource->kebabPlural()}.index).");
        $this->line("  6. Optional local sample data: call {$resource->model}Seeder from database/seeders/DatabaseSeeder.php (it is not registered automatically).");
        $this->line("  7. make test ARGS='--compact tests/Feature/Admin/{$resource->model}CrudTest.php' and make npm ARGS='run check'");

        return self::SUCCESS;
    }

    /**
     * Run the project formatters on the newly created files only.
     *
     * @param  list<PlannedChange>  $changes
     */
    private function format(ResourceGenerator $generator, array $changes): void
    {
        $php = [];
        $frontend = [];
        foreach ($changes as $change) {
            if (! $change->isNew()) {
                continue;
            }

            if (str_ends_with($change->path, '.php')) {
                $php[] = $change->path;
            } elseif (str_ends_with($change->path, '.tsx') || str_ends_with($change->path, '.ts')) {
                $frontend[] = $change->path;
            }
        }

        $formatters = [
            'Pint' => [$generator->path('vendor/bin/pint'), $php],
            'Vite+ fmt' => [$generator->path('node_modules/.bin/vp'), $frontend],
        ];

        foreach ($formatters as $label => [$binary, $paths]) {
            if ($paths === []) {
                continue;
            }

            if (! is_file($binary)) {
                $this->warn("{$label} is not installed; format the generated files before committing.");

                continue;
            }

            $command = $label === 'Pint' ? [$binary, ...$paths] : [$binary, 'fmt', ...$paths];
            $result = Process::path($generator->path(''))->timeout(180)->run($command);

            if (! $result->successful()) {
                $this->warn("{$label} failed; format the generated files before committing.".PHP_EOL.$result->errorOutput());
            }
        }
    }
}
