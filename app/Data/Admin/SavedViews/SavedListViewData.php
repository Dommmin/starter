<?php

namespace App\Data\Admin\SavedViews;

use App\Models\SavedListView;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One saved view in the list header menu.
 */
#[TypeScript]
class SavedListViewData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
    ) {}

    public static function fromModel(SavedListView $view): self
    {
        return new self($view->id, $view->name, $view->url());
    }
}
