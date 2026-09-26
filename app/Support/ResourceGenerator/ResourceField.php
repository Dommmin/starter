<?php

namespace App\Support\ResourceGenerator;

use Illuminate\Support\Str;

/**
 * One column of a generated admin resource, parsed from `--fields`.
 *
 * Booleans and enums are always required (a boolean defaults to false, an
 * enum to its first value), so the form never sends an "empty" value for them.
 */
final readonly class ResourceField
{
    public const array TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'enum'];

    /**
     * @param  list<string>  $enumValues
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public array $enumValues = [],
    ) {}

    public function isRequired(): bool
    {
        return $this->required || $this->type === 'boolean' || $this->type === 'enum';
    }

    /**
     * camelCase name used by Data classes and TypeScript.
     */
    public function property(): string
    {
        return Str::camel($this->name);
    }

    /**
     * Human readable English label, e.g. `is_active` -> `Is active`.
     */
    public function label(): string
    {
        return Str::ucfirst(str_replace('_', ' ', $this->name));
    }

    /**
     * Short class name of the backed enum generated for an enum field.
     */
    public function enumClass(string $model): string
    {
        return $model.Str::studly($this->name);
    }

    /**
     * Enum case name for one of the allowed values, e.g. `in_review` -> `InReview`.
     */
    public static function enumCase(string $value): string
    {
        return Str::studly($value);
    }

    /**
     * Human readable English label of an enum value.
     */
    public static function enumValueLabel(string $value): string
    {
        return Str::ucfirst(str_replace('_', ' ', $value));
    }

    /**
     * Whether the field is shown as a column of the list (long text is not).
     */
    public function isListed(): bool
    {
        return $this->type !== 'text';
    }
}
