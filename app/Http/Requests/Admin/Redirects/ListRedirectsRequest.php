<?php

namespace App\Http\Requests\Admin\Redirects;

use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListRedirectsRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny,App\Models\Redirect`);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single source of the list contract: search columns, sort and filter
     * allowlists, and page size.
     */
    public function listQuery(): ListQuery
    {
        return ListQuery::make()
            ->searchable('source_path', 'target')
            ->sortable(['source_path', 'hits', 'last_hit_at', 'updated_at'], default: 'source_path', defaultDirection: 'asc')
            ->filter('is_active', ['all', 'yes', 'no'], default: 'all', apply: function (Builder $query, string $value): void {
                match ($value) {
                    'yes' => $query->where('is_active', true),
                    'no' => $query->where('is_active', false),
                    default => null,
                };
            })
            ->perPage(15);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->listQuery()->rules();
    }
}
