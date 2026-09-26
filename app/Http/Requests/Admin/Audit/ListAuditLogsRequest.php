<?php

namespace App\Http\Requests\Admin\Audit;

use App\Enums\AuditAction;
use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogsRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny,App\Models\AuditLog`);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single source of the audit list contract: action filter, date sort
     * (newest first) and page size.
     */
    public function listQuery(): ListQuery
    {
        return ListQuery::make()
            ->sortable(['created_at'], default: 'created_at', defaultDirection: 'desc')
            ->filter('action', ['all', ...AuditAction::values()], default: 'all', apply: function (Builder $query, string $value): void {
                if ($value !== 'all') {
                    $query->where('action', $value);
                }
            })
            ->perPage(25);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->listQuery()->rules();
    }
}
