<?php

namespace App\Support\DemoContent;

use App\Actions\Media\DeleteMediaAsset;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\MediaAsset;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DemoMediaSeeder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample DAM assets of {@see DemoMediaSeeder}, matched by their fixed UUID.
 * Only the alt text is editable, so an asset counts as edited when its alt
 * text differs from the seeded one (variant generation and the scan also
 * touch `updated_at`). An asset still in use is kept as well, because its
 * references (`nullOnDelete` foreign keys, rich text `mediaId`) would lose it
 * silently: a cover or an image in the body of any article or page that
 * `--remove-demo` keeps, or the logo / sharing image in the site settings.
 * References from unedited sample articles and pages do not count; those
 * are removed first.
 */
class DemoMedia implements DemoContentProvider
{
    /**
     * Ids of assets referenced by content that stays, computed once per plan.
     *
     * @var array<int, true>|null
     */
    private ?array $usedIds = null;

    public function __construct(
        private readonly DeleteMediaAsset $deleteMediaAsset,
        private readonly DemoArticles $demoArticles,
        private readonly DemoPages $demoPages,
    ) {}

    public function label(): string
    {
        return 'media';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return false;
    }

    public function records(): array
    {
        return array_values(MediaAsset::query()
            ->whereIn('uuid', array_column(DemoMediaSeeder::ASSETS, 'uuid'))
            ->orderBy('id')
            ->get()
            ->all());
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        $asset = $this->asset($record);

        if (isset($this->usedIds()[$asset->id])) {
            return true;
        }

        foreach (DemoMediaSeeder::ASSETS as $definition) {
            if ($definition['uuid'] === $asset->uuid) {
                return $asset->alt !== $definition['alt'];
            }
        }

        return true;
    }

    public function describe(Model $record): string
    {
        return $this->asset($record)->original_name;
    }

    public function delete(Model $record, User $actor): void
    {
        $this->deleteMediaAsset->handle($this->asset($record), $actor);
    }

    /**
     * @return array<int, true>
     */
    private function usedIds(): array
    {
        if ($this->usedIds !== null) {
            return $this->usedIds;
        }

        $removedArticleIds = $this->removableIds($this->demoArticles);
        $removedPageIds = $this->removableIds($this->demoPages);
        $ids = [];

        foreach (Article::query()->whereNotIn('id', $removedArticleIds)->whereNotNull('cover_media_id')->pluck('cover_media_id') as $id) {
            $ids[] = (int) $id;
        }

        $settings = SiteSetting::query()->find(SiteSetting::SINGLETON_ID);
        array_push($ids, ...array_filter([$settings?->logo_media_id, $settings?->og_image_media_id]));

        $bodies = [
            ArticleTranslation::query()->whereNotIn('article_id', $removedArticleIds)->pluck('body'),
            PageTranslation::query()->whereNotIn('page_id', $removedPageIds)->pluck('body'),
        ];

        foreach ($bodies as $collection) {
            foreach ($collection as $body) {
                if (is_array($body)) {
                    self::collectImageIds($body, $ids);
                }
            }
        }

        return $this->usedIds = array_fill_keys($ids, true);
    }

    /**
     * Ids of the sample records the provider removes (unedited ones).
     *
     * @return list<int>
     */
    private function removableIds(DemoContentProvider $provider): array
    {
        $ids = [];

        foreach ($provider->records() as $record) {
            if (! $provider->isModifiedSinceSeed($record)) {
                $ids[] = (int) $record->getKey();
            }
        }

        return $ids;
    }

    /**
     * @param  array<mixed>  $node  Rich text node.
     * @param  list<int>  $ids
     */
    private static function collectImageIds(array $node, array &$ids): void
    {
        $mediaId = $node['attrs']['mediaId'] ?? null;

        if (($node['type'] ?? null) === 'image' && is_int($mediaId)) {
            $ids[] = $mediaId;
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                self::collectImageIds($child, $ids);
            }
        }
    }

    private function asset(Model $record): MediaAsset
    {
        if (! $record instanceof MediaAsset) {
            throw new InvalidArgumentException('Expected a media asset.');
        }

        return $record;
    }
}
