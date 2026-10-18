<?php

namespace App\Repositories\Media;

use App\Contracts\Search\AdminSearchSource;
use App\Data\Admin\Search\AdminSearchResultData;
use App\Enums\MediaStatus;
use App\Models\MediaAsset;
use App\Support\Search\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Named read queries of the Media module.
 */
class MediaAssetRepository implements AdminSearchSource
{
    /**
     * Base query of the admin media list.
     *
     * @return Builder<MediaAsset>
     */
    public function adminListQuery(): Builder
    {
        return MediaAsset::query();
    }

    /**
     * Base query of the rich text image picker: clean images with variants.
     *
     * @return Builder<MediaAsset>
     */
    public function pickerQuery(): Builder
    {
        return MediaAsset::query()->usableImages();
    }

    /**
     * Load an asset with its owner for the details screen.
     */
    public function forEditor(MediaAsset $asset): MediaAsset
    {
        return $asset->load('owner');
    }

    /**
     * Clean images with variants among the given ids, keyed by id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, MediaAsset>
     */
    public function usableImagesByIds(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return MediaAsset::query()
            ->usableImages()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * Number of uploads still waiting for the malware scan result.
     */
    public function quarantinedCount(): int
    {
        return MediaAsset::query()->where('status', MediaStatus::Quarantine->value)->count();
    }

    public function rejectedCount(): int
    {
        return MediaAsset::query()->where('status', MediaStatus::Rejected->value)->count();
    }

    public function model(): string
    {
        return MediaAsset::class;
    }

    /**
     * Assets whose file name or alternative text contains the term.
     */
    public function search(string $term, int $limit): array
    {
        $query = MediaAsset::query()->orderByDesc('id');
        LikeSearch::apply($query, ['original_name', "coalesce(alt, '')"], $term);

        $results = [];
        foreach ($query->limit($limit)->get() as $asset) {
            $results[] = new AdminSearchResultData(
                type: 'media',
                id: $asset->id,
                label: $asset->original_name,
                description: $asset->alt,
                url: route('admin.media.edit', $asset),
            );
        }

        return $results;
    }
}
