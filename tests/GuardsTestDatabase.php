<?php

namespace Tests;

/**
 * Użyj w tests/TestCase.php: `use GuardsTestDatabase;`.
 * Sprawdza zaraz po utworzeniu aplikacji (konfiguracja już wczytana), czyli przed callbackami
 * testów równoległych (tworzenie baz *_test_N) i przed traitami RefreshDatabase,
 * DatabaseMigrations, DatabaseTruncation i DatabaseTransactions, te połączenia:
 * - domyślne (database.default): migrate:fresh, seedowanie i bazy testów równoległych,
 * - z connectionsToTransact() (RefreshDatabase, DatabaseTransactions),
 * - z connectionsToTruncate() (DatabaseTruncation).
 * Listy bierze z tych samych metod co Laravel (także przesłoniętych w klasie testu); bez traitu
 * z właściwości $connectionsToTransact i $connectionsToTruncate. null to połączenie domyślne.
 * Nie widzi połączenia wskazanego w kodzie (migracja z $connection, DB::connection()).
 * beforeEach() z tests/Pest.php działa dopiero po setUp(), więc po migracjach: za późno.
 */
trait GuardsTestDatabase
{
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $config = $this->app['config'];
        $names = [null];
        foreach (['connectionsToTransact', 'connectionsToTruncate'] as $list) {
            if (method_exists($this, $list)) {
                $names = [...$names, ...$this->{$list}()];
            } elseif (property_exists($this, $list)) {
                $names = [...$names, ...$this->{$list}];
            }
        }

        foreach (array_unique(array_map(fn ($name) => $name ?? $config->get('database.default'), $names)) as $name) {
            TestDatabaseGuard::check((string) $name, (array) $config->get("database.connections.{$name}", []));
        }
    }
}
