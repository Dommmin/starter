<?php

namespace App\Http\Requests\Admin\Faqs;

use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListFaqsRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny,App\Models\Faq`);
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
            ->searchable('question')
            ->sortable(['question', 'position', 'created_at'], default: 'created_at', defaultDirection: 'desc')
            ->filter('published', ['all', 'yes', 'no'], default: 'all', apply: function (Builder $query, string $value): void {
                match ($value) {
                    'yes' => $query->where('published', true),
                    'no' => $query->where('published', false),
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
