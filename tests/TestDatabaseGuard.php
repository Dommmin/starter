<?php

namespace Tests;

use Illuminate\Support\ConfigurationUrlParser;
use RuntimeException;

/**
 * Testy odmawiają bazy, która nie jest testowa. Dozwolone:
 * - sqlite w pamięci (:memory:) albo plik o nazwie testing, *_test lub *_testing,
 * - inny silnik: baza testing, *_test lub *_testing na hoście z HOSTS.
 * Domyślna baza z phpunit.xml to sqlite :memory:; MySQL i PostgreSQL ustawiasz zmiennymi
 * środowiskowymi (kontener, CI), np. DB_CONNECTION=mysql DB_HOST=mysql DB_DATABASE=app_test.
 */
final class TestDatabaseGuard
{
    /**
     * Hosty bazy testowej: lokalne i nazwy usług bazy w compose i CI. Jedyna lista do edycji.
     * Lista ma odpowiadać usługom bazy z compose i CI projektu: brakującą dopisz, nieużywane
     * nazwy usuń (każda zbędna nazwa to host, na którym strażnik przepuści bazę *_test).
     */
    public const HOSTS = ['127.0.0.1', 'localhost', '::1', 'mysql', 'postgres'];

    /** Nazwa bazy (albo pliku sqlite bez rozszerzenia): testing, test, *_test, *_testing. */
    private const NAME = '/(^|_)test(ing)?$/i';

    /**
     * Czysta funkcja: konfiguracja połączenia z config/database.php na wejściu, wyjątek przy
     * bazie, która nie jest testowa. Nie łączy się z bazą.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    public static function check(string $connection, array $config): void
    {
        // DB_URL nadpisuje pola połączenia tak samo jak w Laravelu (ConnectionFactory).
        if (! empty($config['url'])) {
            $config = (new ConfigurationUrlParser)->parseConfiguration($config);
        }

        $driver = (string) ($config['driver'] ?? '');
        $database = (string) ($config['database'] ?? '');

        if ($driver === 'sqlite') {
            // To samo rozpoznanie bazy w pamięci co Illuminate\Database\Connectors\SQLiteConnector.
            if ($database === ':memory:' || str_contains($database, '?mode=memory') || str_contains($database, '&mode=memory')) {
                return;
            }
            if (preg_match(self::NAME, pathinfo($database, PATHINFO_FILENAME)) === 1) {
                return;
            }
            throw self::refusal($connection, $database, 'plik lokalny', 'plik sqlite nie nazywa się testing, *_test ani *_testing');
        }

        $hosts = self::hosts($config);
        $shown = $hosts === [] ? '(brak)' : implode(', ', $hosts);

        if (preg_match(self::NAME, $database) !== 1) {
            throw self::refusal($connection, $database, $shown, 'nazwa bazy to nie testing, *_test ani *_testing');
        }
        if ($hosts === []) {
            throw self::refusal($connection, $database, $shown, 'brak hosta bazy');
        }
        foreach ($hosts as $host) {
            if (! in_array(strtolower($host), self::HOSTS, true)) {
                throw self::refusal($connection, $database, $shown, "host {$host} nie jest lokalny ani nie jest usługą z TestDatabaseGuard::HOSTS");
            }
        }
    }

    /**
     * Hosty połączenia: host (tekst albo lista) oraz host z sekcji read i write.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function hosts(array $config): array
    {
        $hosts = [];
        foreach ([$config, $config['read'] ?? [], $config['write'] ?? []] as $part) {
            foreach ((array) (is_array($part) ? ($part['host'] ?? []) : []) as $host) {
                if ((string) $host !== '') {
                    $hosts[] = (string) $host;
                }
            }
        }

        return array_values(array_unique($hosts));
    }

    private static function refusal(string $connection, string $database, string $host, string $reason): RuntimeException
    {
        return new RuntimeException(
            "Strażnik bazy testowej: odmowa ({$reason}). Połączenie: {$connection}, baza: {$database}, host: {$host}. "
            .'Testy działają tylko na sqlite :memory: albo na bazie testing/*_test/*_testing na hoście lokalnym '
            .'lub usłudze bazy z compose i CI (tests/TestDatabaseGuard.php, HOSTS). '
            .'Ustaw DB_CONNECTION i DB_DATABASE w zmiennych środowiskowych, nie zmieniaj strażnika.'
        );
    }
}
