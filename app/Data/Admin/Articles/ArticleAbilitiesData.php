<?php

namespace App\Data\Admin\Articles;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * UI hints only; every operation is authorized again on the backend.
 */
#[TypeScript]
class ArticleAbilitiesData extends Data
{
    /**
     * @param  bool  $suggestSeo  The AI assistant may propose the meta description and slug.
     * @param  bool  $suggestTranslation  The AI assistant may translate the default language version into another language.
     * @param  bool  $rewriteText  The AI assistant may rewrite plain text fields in a fixed mode.
     */
    public function __construct(
        public bool $create,
        public bool $update,
        public bool $publish,
        public bool $delete,
        public bool $suggestSeo = false,
        public bool $suggestTranslation = false,
        public bool $rewriteText = false,
    ) {}
}
