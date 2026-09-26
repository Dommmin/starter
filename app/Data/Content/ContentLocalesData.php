<?php

namespace App\Data\Content;

use App\Services\Localization\LocalizationConfig;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Content locales for editor screens: every active public locale in
 * configuration order and the default one, whose translation is required.
 */
#[TypeScript]
class ContentLocalesData extends Data
{
    /**
     * @param  list<ContentLocaleData>  $available
     */
    public function __construct(
        public array $available,
        public string $default,
    ) {}

    public static function fromConfig(LocalizationConfig $config): self
    {
        $available = [];

        foreach ($config->getAvailableLocalesForArea('public') as $locale) {
            $available[] = new ContentLocaleData(
                code: $locale['code'],
                name: $locale['name'],
                native: $locale['native'],
                dir: $locale['dir'],
            );
        }

        return new self(available: $available, default: $config->getPublicDefault());
    }
}
