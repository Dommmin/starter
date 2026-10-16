<?php

namespace App\Models;

use App\Enums\ContactFieldType;
use App\Enums\ContactMessageStatus;
use App\Enums\ContactWebhookStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message submitted through the public contact form (Contact module). The
 * record is the source of truth for delivery; the mail is sent by the
 * `SendContactMessage` job.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $message
 * @property string $locale
 * @property ContactMessageStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable|null $sent_at
 * @property ContactWebhookStatus|null $webhook_status Null when no webhook is configured.
 * @property int $webhook_attempts
 * @property CarbonImmutable|null $webhook_delivered_at
 * @property int|null $contact_form_id Null for messages from before configurable forms.
 * @property array<string, string|bool>|null $fields Extra field values keyed by field key.
 * @property-read ContactForm|null $form
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'message', 'locale', 'status', 'attempts', 'last_error', 'sent_at', 'webhook_status', 'contact_form_id', 'fields'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'webhook_attempts' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'webhook_status' => ContactWebhookStatus::class,
            'webhook_attempts' => 'integer',
            'webhook_delivered_at' => 'datetime',
            'contact_form_id' => 'integer',
            'fields' => 'array',
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
     * Extra values as display rows in the locale: the field label (the key
     * when the field was removed since), the option label of a select and
     * yes/no for a checkbox. Requires `form.fields` to be loaded.
     *
     * @return list<array{label: string, value: string}>
     */
    public function extraFieldRows(string $locale, string $fallback): array
    {
        $fields = $this->form?->fields->keyBy('key');
        $rows = [];

        foreach ($this->fields ?? [] as $key => $value) {
            /** @var ContactFormField|null $field */
            $field = $fields?->get($key);
            $display = match (true) {
                is_bool($value) => __($value ? 'common.answers.yes' : 'common.answers.no', [], $locale),
                $field?->type === ContactFieldType::Select => self::optionLabel($field, $value, $locale, $fallback),
                default => (string) $value,
            };

            $rows[] = ['label' => $field?->labelIn($locale, $fallback) ?? (string) $key, 'value' => $display];
        }

        return $rows;
    }

    private static function optionLabel(ContactFormField $field, string $value, string $locale, string $fallback): string
    {
        foreach ($field->options ?? [] as $option) {
            if ($option['value'] === $value) {
                return $option['label'][$locale] ?? $option['label'][$fallback] ?? $value;
            }
        }

        return $value;
    }
}
