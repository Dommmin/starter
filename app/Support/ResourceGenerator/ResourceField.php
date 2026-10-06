<?php

namespace App\Support\ResourceGenerator;

use Illuminate\Support\Str;

/**
 * One column of a generated admin resource, parsed from `--fields`.
 *
 * Booleans and enums are always required (a boolean defaults to false, an
 * enum to its first value), so the form never sends an "empty" value for them.
 * Images and rich text are always optional.
 */
final readonly class ResourceField
{
    public const array TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'enum', 'belongsTo', 'belongsToMany', 'image', 'richtext'];

    /**
     * Tones of the `Badge` primitive (`BadgeProps['tone']` in
     * resources/js/design-system/primitives/badge.tsx).
     */
    public const array BADGE_TONES = ['neutral', 'primary', 'success', 'danger', 'outline'];

    /**
     * @param  list<string>  $enumValues
     * @param  array<string, string>  $enumTones  Badge tone per enum value (unlisted values are neutral).
     * @param  string|null  $relatedModel  Short class name in App\Models of a belongsTo or belongsToMany target.
     * @param  string|null  $relatedLabel  Column of the related model shown as its label.
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public array $enumValues = [],
        public array $enumTones = [],
        public ?string $relatedModel = null,
        public ?string $relatedLabel = null,
    ) {}

    public function isRequired(): bool
    {
        return $this->required || $this->type === 'boolean' || $this->type === 'enum';
    }

    /**
     * Database column and request key, e.g. `category` (belongsTo) -> `category_id`,
     * `cover` (image) -> `cover_media_id`. A belongsToMany field has no column
     * on the resource table; its request key lists the related ids, e.g.
     * `tags` -> `tag_ids`.
     */
    public function column(): string
    {
        return match ($this->type) {
            'belongsTo' => $this->name.'_id',
            'belongsToMany' => Str::singular($this->name).'_ids',
            'image' => $this->name.'_media_id',
            default => $this->name,
        };
    }

    /**
     * Pivot table of a belongsToMany field, Laravel's default name: both
     * singular snake_case model names in alphabetical order, e.g. `product_tag`.
     */
    public function pivotTable(string $model): string
    {
        $names = [Str::snake($model), Str::snake((string) $this->relatedModel)];
        sort($names);

        return implode('_', $names);
    }

    /**
     * Table of the related model (no custom table names are supported).
     */
    public function relatedTable(): string
    {
        return $this->type === 'image' ? 'media_assets' : Str::snake(Str::pluralStudly($this->relatedClass()));
    }

    /**
     * camelCase name used by Data classes and TypeScript for the column value.
     */
    public function property(): string
    {
        return Str::camel($this->column());
    }

    /**
     * Eloquent relation method of a belongsTo or image field, e.g. `category`.
     */
    public function relation(): string
    {
        return Str::camel($this->name);
    }

    public function isRelation(): bool
    {
        return $this->type === 'belongsTo' || $this->type === 'image';
    }

    /**
     * Related model short class name (belongsTo target or MediaAsset).
     */
    public function relatedClass(): string
    {
        return $this->type === 'image' ? 'MediaAsset' : (string) $this->relatedModel;
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
     * Badge tone of one enum value.
     */
    public function toneOf(string $value): string
    {
        return $this->enumTones[$value] ?? 'neutral';
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
     * Whether the field is shown as a column of the list (long text, rich
     * text and images are not; a belongsTo shows the related label).
     */
    public function isListed(): bool
    {
        return ! in_array($this->type, ['text', 'richtext', 'image'], true);
    }

    /**
     * Whether the list may be sorted by the column.
     */
    public function isSortable(): bool
    {
        return $this->isListed() && $this->type !== 'belongsTo';
    }

    /**
     * Whether the field is a column of the CSV export (everything but rich
     * text and images; a belongsTo exports the related label).
     */
    public function isExported(): bool
    {
        return ! in_array($this->type, ['richtext', 'image'], true);
    }
}
