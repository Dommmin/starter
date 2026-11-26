<?php

namespace App\Http\Requests\Admin\Assistant;

use App\Enums\AssistantFeature;
use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alt text suggestion for one media asset. 404 while the feature is
 * unavailable; only users who may update the asset may ask. The request has
 * no input: the image is read from storage.
 */
class SuggestMediaAltTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(AssistantFeature::MediaAltText->isAvailable(), 404);

        $asset = $this->route('mediaAsset');

        return $asset instanceof MediaAsset && $this->user()?->can('update', $asset) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
