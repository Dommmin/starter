<?php

namespace App\Support\Import;

use App\Contracts\Import\ImportDefinition;

/**
 * Modules with a CSV import, by route key.
 */
final class ImportDefinitions
{
    /**
     * @var list<class-string<ImportDefinition>>
     */
    public const array DEFINITIONS = [
        FaqImport::class,
        RedirectImport::class,
        TagImport::class,
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (string $class): string => app($class)->key(), self::DEFINITIONS);
    }

    public static function find(string $key): ?ImportDefinition
    {
        foreach (self::DEFINITIONS as $class) {
            $definition = app($class);

            if ($definition->key() === $key) {
                return $definition;
            }
        }

        return null;
    }
}
