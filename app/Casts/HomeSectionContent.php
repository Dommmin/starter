<?php

namespace App\Casts;

use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * Casts `home_sections.content` to the closed Data class of the section
 * `type`. Only an instance of that class can be stored, so arbitrary arrays
 * or unknown keys never reach the database; the JSON is the Data output.
 *
 * @implements CastsAttributes<Data, Data>
 */
class HomeSectionContent implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Data
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);

        return self::type($attributes)->contentClass()::from(is_array($decoded) ? $decoded : []);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $class = self::type($attributes)->contentClass();

        if (! $value instanceof $class) {
            throw new InvalidArgumentException(sprintf(
                'Content of a [%s] home section must be an instance of [%s].',
                self::type($attributes)->value,
                $class,
            ));
        }

        return [$key => json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
    }

    /**
     * The section type must be set before the content.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function type(array $attributes): HomeSectionType
    {
        $type = $attributes['type'] ?? null;

        if ($type instanceof HomeSectionType) {
            return $type;
        }

        if (is_string($type) && ($resolved = HomeSectionType::tryFrom($type)) !== null) {
            return $resolved;
        }

        throw new InvalidArgumentException('A home section needs a known `type` before its content ('.HomeSection::class.').');
    }
}
