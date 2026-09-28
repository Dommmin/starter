<?php

namespace App\Support\Csv;

/**
 * CSV cell values safe to open in a spreadsheet (CSV/formula injection).
 *
 * Text starting with `=`, `+`, `-`, `@`, a tab or a carriage return is
 * prefixed with an apostrophe so spreadsheet applications show it as text
 * instead of evaluating it. Native numbers and plain numeric strings (e.g. a
 * `decimal:2` cast such as `-12.50`) cannot form a formula and stay unchanged.
 */
final class CsvCell
{
    private const array FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function safe(string|int|float|bool|null $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }
}
