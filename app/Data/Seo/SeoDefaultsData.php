<?php

namespace App\Data\Seo;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Shared `seo` Inertia prop: site-wide defaults the `Seo` primitive falls
 * back to when a page does not provide its own values.
 */
#[TypeScript]
class SeoDefaultsData extends Data
{
    /**
     * @param  string  $canonical  Absolute URL of the current request without query string.
     * @param  string|null  $defaultImage  Absolute URL of the default og:image.
     * @param  string  $defaultTitle  Title of pages without their own (home page): the SEO title of the site settings or the site name.
     * @param  string|null  $defaultDescription  Meta description of pages without their own.
     */
    public function __construct(
        public string $siteName,
        public string $canonical,
        public ?string $defaultImage,
        public string $defaultTitle,
        public ?string $defaultDescription,
        public SeoOrganizationData $organization,
    ) {}
}
