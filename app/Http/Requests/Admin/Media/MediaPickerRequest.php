<?php

namespace App\Http\Requests\Admin\Media;

use App\Support\Listing\ListQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query of the rich text image picker: clean images with variants only.
 */
class MediaPickerRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny,App\Models\MediaAsset`);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function listQuery(): ListQuery
    {
        return ListQuery::make()
            ->searchable('original_name', 'alt')
            ->sortable(['created_at'], default: 'created_at', defaultDirection: 'desc')
            ->perPage(24);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->listQuery()->rules();
    }
}
