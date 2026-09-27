<?php

namespace App\Http\Requests\Admin\Contact;

use App\Enums\ContactMessageStatus;
use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListContactMessagesRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route
     * (`can:viewAny,App\Models\ContactMessage`); this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single source of the list contract: search by sender, status filter,
     * newest first.
     */
    public function listQuery(): ListQuery
    {
        return ListQuery::make()
            ->searchable('name', 'email')
            ->sortable(['created_at'], default: 'created_at', defaultDirection: 'desc')
            ->filter('status', ['all', ...ContactMessageStatus::values()], default: 'all', apply: function (Builder $query, string $value): void {
                if ($value !== 'all') {
                    $query->where('status', $value);
                }
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
