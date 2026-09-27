<?php

namespace App\Data\Admin\Media;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props of the `admin/media/edit` screen.
 */
#[TypeScript]
class MediaAssetEditorData extends Data
{
    public function __construct(
        public MediaAssetDetailData $asset,
        public MediaAssetAbilitiesData $can,
    ) {}
}
