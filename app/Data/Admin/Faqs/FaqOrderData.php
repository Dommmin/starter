<?php

namespace App\Data\Admin\Faqs;

use App\Data\Content\ContentLocalesData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/faqs/order` screen: every faq, unpaginated, in the
 * shared order used by every public locale list.
 */
#[TypeScript]
class FaqOrderData extends Data
{
    /**
     * @param  list<FaqOrderItemData>  $items
     */
    public function __construct(
        public array $items,
        public bool $canReorder,
        public ContentLocalesData $locales,
    ) {}
}
