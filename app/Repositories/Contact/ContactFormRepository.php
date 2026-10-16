<?php

namespace App\Repositories\Contact;

use App\Data\Contact\PublicContactFormData;
use App\Models\ContactForm;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read side of the configurable contact forms.
 */
class ContactFormRepository
{
    public function __construct(private readonly LocalizationConfig $localization) {}

    /**
     * Enabled form with its ordered fields; null when the key is unknown or
     * the form is disabled.
     */
    public function enabled(string $key): ?ContactForm
    {
        return ContactForm::query()
            ->where('key', $key)
            ->where('enabled', true)
            ->with('fields')
            ->first();
    }

    /**
     * Public definition of an enabled form in the locale, falling back to
     * the default form when the key is unknown or disabled.
     */
    public function publicForm(?string $key, string $locale): ?PublicContactFormData
    {
        $form = ($key !== null ? $this->enabled($key) : null) ?? $this->enabled(ContactForm::DEFAULT_KEY);

        return $form === null
            ? null
            : PublicContactFormData::fromForm($form, $locale, $this->localization->getPublicFallback());
    }

    /**
     * Every form for the admin list and section selects, by key.
     *
     * @return Collection<int, ContactForm>
     */
    public function all(): Collection
    {
        return ContactForm::query()->withCount('fields')->orderBy('key')->get();
    }
}
