<?php

namespace App\Http\Requests\Admin\Media;

use App\Enums\MediaStatus;
use App\Models\MediaAsset;
use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListMediaAssetsRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny,App\Models\MediaAsset`);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single source of the list contract: search by file name, status and
     * type filters, sort allowlist and page size.
     */
    public function listQuery(): ListQuery
    {
        return ListQuery::make()
            ->searchable('original_name')
            ->sortable(['original_name', 'size', 'created_at'], default: 'created_at', defaultDirection: 'desc')
            ->filter('status', ['all', ...array_column(MediaStatus::cases(), 'value')], default: 'all', apply: function (Builder $query, string $value): void {
                if ($value !== 'all') {
                    $query->where('status', $value);
                }
            })
            ->filter('type', ['all', 'image', 'document'], default: 'all', apply: function (Builder $query, string $value): void {
                match ($value) {
                    'image' => $query->whereIn('mime', MediaAsset::IMAGE_MIMES),
                    'document' => $query->whereNotIn('mime', MediaAsset::IMAGE_MIMES),
                    default => null,
                };
            })
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
