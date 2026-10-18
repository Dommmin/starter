<?php

namespace App\Repositories\Content;

use App\Enums\TrashedContentType;
use App\Models\Article;
use App\Models\Page;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read queries of the content trash (pages and articles).
 */
class TrashRepository
{
    /**
     * Trashed records of one type with every translation, newest first.
     *
     * @return Collection<int, Page>|Collection<int, Article>
     */
    public function trashed(TrashedContentType $type): Collection
    {
        return $this->query($type)->latest('deleted_at')->latest('id')->get();
    }

    /**
     * Find a trashed record of the type with its translations.
     */
    public function findTrashed(TrashedContentType $type, int $id): Page|Article|null
    {
        return $this->query($type)->whereKey($id)->first();
    }

    /**
     * Records of the type in the trash since before the moment.
     *
     * @return Collection<int, Page>|Collection<int, Article>
     */
    public function trashedBefore(TrashedContentType $type, CarbonInterface $moment): Collection
    {
        return $this->query($type)->where('deleted_at', '<', $moment)->orderBy('id')->get();
    }

    /**
     * @return Builder<Page>|Builder<Article>
     */
    private function query(TrashedContentType $type): Builder
    {
        $scope = $type->translationScope();

        return $type->model()::query()
            ->onlyTrashed()
            ->with(['translations' => fn ($query) => $query->withoutGlobalScope($scope)->orderBy('locale')]);
    }
}
