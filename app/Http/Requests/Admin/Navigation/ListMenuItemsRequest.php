<?php

namespace App\Http\Requests\Admin\Navigation;

use App\Enums\MenuLocation;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Selected menu (`location`, `locale`) of the tree and of the create form.
 * Defaults: the header, and the admin language when it is a public locale,
 * otherwise the default public locale.
 */
class ListMenuItemsRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny` / `can:create`);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'location' => ['sometimes', Rule::enum(MenuLocation::class)],
            'locale' => ['sometimes', 'string', Rule::in(app(LocalizationConfig::class)->getPublicLocales())],
        ];
    }

    public function location(): MenuLocation
    {
        return MenuLocation::tryFrom($this->string('location')->toString()) ?? MenuLocation::Header;
    }

    public function locale(): string
    {
        $config = app(LocalizationConfig::class);
        $locale = $this->string('locale')->toString();

        if ($config->isPublicLocale($locale)) {
            return $locale;
        }

        $adminLocale = app()->getLocale();

        return $config->isPublicLocale($adminLocale) ? $adminLocale : $config->getPublicDefault();
    }
}
