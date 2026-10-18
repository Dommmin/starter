<?php

namespace App\Data\Listing;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Newest records of one hasMany relation of a generated admin resource,
 * shown as a tab of the parent's edit screen with a link to the full list.
 */
#[TypeScript]
class RelatedRecordsData extends Data
{
    /** Records listed in the tab; `total` counts all of them. */
    public const int LIMIT = 20;

    /**
     * @param  string  $relation  Relation method, e.g. `products`.
     * @param  list<RelatedRecordData>  $items  Newest first, at most LIMIT.
     */
    public function __construct(
        public string $relation,
        public int $total,
        public array $items,
        public string $listUrl,
    ) {}

    /**
     * @template TRelated of Model
     *
     * @param  HasMany<TRelated, covariant Model>  $relation
     * @param  Closure(TRelated): string  $url  Edit URL of one related record.
     */
    public static function fromRelation(string $name, HasMany $relation, string $labelColumn, Closure $url, string $listUrl): self
    {
        /** @var Collection<int, TRelated> $records */
        $records = (clone $relation)->latest('id')->limit(self::LIMIT)->get();

        $items = [];
        foreach ($records as $record) {
            $items[] = new RelatedRecordData(
                id: (int) $record->getKey(),
                label: (string) $record->getAttribute($labelColumn),
                url: $url($record),
            );
        }

        return new self(
            relation: $name,
            total: $relation->count(),
            items: $items,
            listUrl: $listUrl,
        );
    }
}
