<?php

namespace App\Models;

use App\Casts\JsonArray;
use Carbon\CarbonImmutable;
use Database\Factories\SavedListViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Named filters, search and sort of an admin list, private to its owner.
 *
 * @property int $id
 * @property int $user_id
 * @property string $list Route name of the list, e.g. `admin.articles.index`.
 * @property string $name
 * @property array<string, string> $query
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['list', 'name', 'query'])]
class SavedListView extends Model
{
    /** @use HasFactory<SavedListViewFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query' => JsonArray::class,
        ];
    }

    /**
     * Owner of the view; set by the server, never from the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * URL of the list with the saved parameters.
     */
    public function url(): string
    {
        return route($this->list, $this->query);
    }
}
