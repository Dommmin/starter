<?php

namespace App\Http\Requests\Admin\Media;

use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaAssetRequest extends FormRequest
{
    /**
     * The route checks `update`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        $asset = $this->route('mediaAsset');

        return $asset instanceof MediaAsset && ($this->user()?->can('update', $asset) ?? false);
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic locking).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'updated_at' => ['required', 'string', 'date'],
            'alt' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function alt(): ?string
    {
        $alt = $this->validated('alt');

        return is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }
}
