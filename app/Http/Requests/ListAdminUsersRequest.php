<?php

namespace App\Http\Requests;

use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListAdminUsersRequest extends FormRequest
{
    /**
     * Authorization is enforced by the admin route group middleware
     * (EnsureCanAccessAdminPanel); this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single source of the user list contract: search columns, sort and
     * filter allowlists, and page size.
     */
    public function listQuery(): ListQuery
    {
        return ListQuery::make()
            ->searchable('name', 'email')
            ->sortable(['name', 'email', 'created_at'], default: 'created_at', defaultDirection: 'desc')
            ->filter('verified', ['all', 'verified', 'unverified'], default: 'all', apply: function (Builder $query, string $value): void {
                match ($value) {
                    'verified' => $query->whereNotNull('email_verified_at'),
                    'unverified' => $query->whereNull('email_verified_at'),
                    default => null,
                };
            })
            ->perPage(10);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->listQuery()->rules();
    }
}
