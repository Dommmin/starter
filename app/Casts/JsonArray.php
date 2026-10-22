<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * The `array` cast with a dirty check that ignores the order of object keys.
 *
 * MySQL stores the `json` type normalized (keys sorted, its own spacing), so
 * the value read back never matches the encoded array byte for byte and the
 * built-in strict comparison would mark every save as a change (needless
 * updates, `updated_at` bumps and audit entries). PostgreSQL and SQLite keep
 * the text, where the result is the same as with `array`.
 *
 * @implements CastsAttributes<array<array-key, mixed>|null, array<array-key, mixed>|null>
 */
class JsonArray implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR)];
    }

    /**
     * Raw stored values are equal when they decode to the same data.
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return self::equivalent($firstValue, $secondValue);
    }

    /**
     * Whether two raw JSON values hold the same data, regardless of object key
     * order and formatting. List order still matters.
     */
    public static function equivalent(mixed $first, mixed $second): bool
    {
        if ($first === null || $second === null) {
            return $first === $second;
        }

        return self::canonical(json_decode((string) $first, true)) === self::canonical(json_decode((string) $second, true));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
