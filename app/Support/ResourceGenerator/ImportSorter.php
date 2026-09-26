<?php

namespace App\Support\ResourceGenerator;

/**
 * Sorts, de-duplicates and compacts the top-level `use` block of a PHP file
 * the same way Pint's `ordered_imports` (alpha) does.
 */
final class ImportSorter
{
    /**
     * @param  list<string>  $add  Fully qualified class names to import as well.
     */
    public static function sort(string $php, array $add = []): string
    {
        $lines = explode("\n", $php);
        $first = null;
        $last = null;

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, 'use ') && str_ends_with(rtrim($line), ';')) {
                $first ??= $index;
                $last = $index;

                continue;
            }

            if ($first !== null && trim($line) !== '') {
                break;
            }
        }

        if ($first === null || $last === null) {
            return $php;
        }

        $imports = [];
        foreach (array_slice($lines, $first, $last - $first + 1) as $line) {
            if (trim($line) !== '') {
                $imports[] = rtrim($line);
            }
        }

        foreach ($add as $class) {
            $imports[] = 'use '.ltrim($class, '\\').';';
        }

        $imports = array_values(array_unique($imports));
        usort($imports, fn (string $a, string $b): int => strcasecmp(
            str_replace('\\', ' ', substr($a, 4, -1)),
            str_replace('\\', ' ', substr($b, 4, -1)),
        ));

        array_splice($lines, $first, $last - $first + 1, $imports);

        return implode("\n", $lines);
    }
}
