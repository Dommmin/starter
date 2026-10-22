<?php

namespace App\Models;

use App\Casts\JsonArray;
use Carbon\CarbonImmutable;
use Database\Factories\ContactFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Public contact form (e.g. `contact`, `quote`): the fixed name, e-mail and
 * message plus ordered extra fields. Managed by administrators.
 *
 * @property int $id
 * @property string $key Stable identifier used by home sections and submissions.
 * @property array<string, string> $title Keyed by locale.
 * @property bool $enabled
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ContactFormField> $fields
 */
#[Fillable(['key', 'title', 'enabled'])]
class ContactForm extends Model
{
    /** @use HasFactory<ContactFormFactory> */
    use HasFactory;

    /** Key of the form used when a section or submission names none. */
    public const string DEFAULT_KEY = 'contact';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => JsonArray::class,
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ContactFormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(ContactFormField::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Title in the locale, then the fallback locale, then the key.
     */
    public function titleIn(string $locale, string $fallback): string
    {
        return $this->title[$locale] ?? $this->title[$fallback] ?? $this->key;
    }
}
