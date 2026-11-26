<?php

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\GuardsTestDatabase;
use Tests\TestCase;
use Tests\TestDatabaseGuard;

test('TestCase używa strażnika bazy testowej', function () {
    expect(class_uses(TestCase::class))->toContain(GuardsTestDatabase::class)
        // Własne refreshApplication() w TestCase przesłoniłoby metodę traitu i wyłączyło strażnika.
        ->and((new ReflectionMethod(TestCase::class, 'refreshApplication'))->getFileName())
        ->toEndWith('GuardsTestDatabase.php');
});

test('strażnik odmawia bazy, która nie jest testowa', function (array $config) {
    expect(fn () => TestDatabaseGuard::check('mysql', $config))
        ->toThrow(RuntimeException::class, 'Strażnik bazy testowej: odmowa');
})->with([
    'baza app na 127.0.0.1' => [['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'app']],
    'app_test na zdalnym hoście' => [['driver' => 'mysql', 'host' => 'prod-db.example.com', 'database' => 'app_test']],
    'DB_URL na zdalny host' => [['driver' => 'mysql', 'url' => 'mysql://u:p@prod-db.example.com:3306/app_test', 'host' => '127.0.0.1', 'database' => 'app_test']],
    'zdalny host do zapisu' => [['driver' => 'pgsql', 'read' => ['host' => ['127.0.0.1']], 'write' => ['host' => ['prod-db.example.com']], 'database' => 'app_test']],
    'plik sqlite aplikacji' => [['driver' => 'sqlite', 'database' => '/var/www/database/database.sqlite']],
]);

test('strażnik przepuszcza bazę testową', function (array $config) {
    expect(fn () => TestDatabaseGuard::check('testing', $config))->not->toThrow(RuntimeException::class);
})->with([
    'sqlite :memory:' => [['driver' => 'sqlite', 'database' => ':memory:']],
    'app_testing na 127.0.0.1' => [['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'app_testing']],
    'app_testing na usłudze mysql' => [['driver' => 'mysql', 'host' => 'mysql', 'database' => 'app_testing']],
    'starter_testing na usłudze postgres' => [['driver' => 'pgsql', 'host' => 'postgres', 'database' => 'starter_testing']],
    'plik sqlite testing' => [['driver' => 'sqlite', 'database' => '/var/www/database/testing.sqlite']],
]);

/** Klasa bazowa zamiast aplikacji: refreshApplication() wczytuje tylko podaną konfigurację baz. */
abstract class GuardsTestDatabaseHarness
{
    public array $app = [];

    public function __construct(private array $connections) {}

    public function boot(): void
    {
        $this->refreshApplication();
    }

    protected function refreshApplication()
    {
        $this->app['config'] = new Repository(['database' => [
            'default' => 'sqlite',
            'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'], ...$this->connections],
        ]]);
    }
}

function reportingDatabase(string $database): array
{
    return ['reporting' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => $database]];
}

test('strażnik sprawdza połączenia z $connectionsToTruncate (DatabaseTruncation)', function () {
    $test = fn (string $database) => new class(reportingDatabase($database)) extends GuardsTestDatabaseHarness
    {
        use DatabaseTruncation, GuardsTestDatabase;

        protected array $connectionsToTruncate = ['reporting'];
    };

    expect(fn () => $test('reporting')->boot())
        ->toThrow(RuntimeException::class, 'Połączenie: reporting, baza: reporting,')
        ->and(fn () => $test('reporting_test')->boot())->not->toThrow(RuntimeException::class);
});

test('strażnik sprawdza przesłonięte connectionsToTruncate() i $connectionsToTransact', function () {
    $truncate = new class(reportingDatabase('reporting')) extends GuardsTestDatabaseHarness
    {
        use DatabaseTruncation, GuardsTestDatabase;

        protected function connectionsToTruncate(): array
        {
            return [null, 'reporting'];
        }
    };
    $transact = new class(reportingDatabase('reporting')) extends GuardsTestDatabaseHarness
    {
        use GuardsTestDatabase;

        protected array $connectionsToTransact = ['reporting'];
    };

    expect(fn () => $truncate->boot())->toThrow(RuntimeException::class, 'Połączenie: reporting,')
        ->and(fn () => $transact->boot())->toThrow(RuntimeException::class, 'Połączenie: reporting,');
});
