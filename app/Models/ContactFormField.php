<?php

namespace App\Models;

use App\Casts\JsonArray;
use App\Enums\ContactFieldType;
use Carbon\CarbonImmutable;
use Database\Factories\ContactFormFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extra field of a contact form. `options` (select only) lists the allowed
 * values with their labels per locale.
 *
 * @property int $id
 * @property int $contact_form_id
 * @property string $key Submitted as `fields.{key}`.
 * @property ContactFieldType $type
 * @property bool $required
 * @property int $position
 * @property array<string, string> $label Keyed by locale.
 * @property list<array{value: string, label: array<string, string>}>|null $options
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ContactForm $form
 */
#[Fillable(['key', 'type', 'required', 'position', 'label', 'options'])]
class ContactFormField extends Model
{
    /** @use HasFactory<ContactFormFieldFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_form_id' => 'integer',
            'type' => ContactFieldType::class,
            'required' => 'boolean',
            'position' => 'integer',
            'label' => JsonArray::class,
            'options' => JsonArray::class,
        ];
    }

    /**
     * @return BelongsTo<ContactForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(ContactForm::class, 'contact_form_id');
    }

    /**
     * Label in the locale, then the fallback locale, then the key.
     */
    public function labelIn(string $locale, string $fallback): string
    {
        return $this->label[$locale] ?? $this->label[$fallback] ?? $this->key;
    }

    /**
     * Allowed values of a select field.
     *
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_map(fn (array $option): string => $option['value'], $this->options ?? []);
    }
}
