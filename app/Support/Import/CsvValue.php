<?php

namespace App\Support\Import;

/**
 * Conversions of raw CSV cells; a value that cannot be converted is kept
 * as text so validation reports it at its cell.
 */
final class CsvValue
{
    private const array TRUE_WORDS = ['1', 'true', 'yes', 'tak', 'ja'];

    private const array FALSE_WORDS = ['0', 'false', 'no', 'nie', 'nein'];

    public static function text(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    public static function integer(?string $value): int|string|null
    {
        $value = self::text($value);

        return $value !== null && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value;
    }

    public static function boolean(?string $value, bool $default): bool|string
    {
        $value = self::text($value);

        if ($value === null) {
            return $default;
        }

        $lower = mb_strtolower($value);

        return match (true) {
            in_array($lower, self::TRUE_WORDS, true) => true,
            in_array($lower, self::FALSE_WORDS, true) => false,
            default => $value,
        };
    }
}
