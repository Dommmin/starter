<?php

namespace App\Services\Content;

use App\Data\Admin\Quality\ContentIssueData;
use App\Data\Admin\Quality\ContentQualityData;
use App\Enums\ContentIssueType;
use App\Enums\MediaStatus;
use App\Models\ArticleTranslation;
use App\Models\MediaAsset;
use App\Models\PageSection;
use App\Models\PageTranslation;
use Illuminate\Support\Str;

/**
 * Problems visitors or search engines would notice. Checks published
 * translations (drafts are unfinished by nature) and clean images of the
 * media library. Links inside page sections are not checked.
 */
class ContentQualityReport
{
    public const int LIMIT = 200;

    public function __construct(private readonly InternalLinkChecker $links) {}

    public function build(): ContentQualityData
    {
        /** @var array<string, list<ContentIssueData>> $found */
        $found = [];

        $this->translations(PageTranslation::query()->published()->with('page')->lazyById(200), 'admin.pages.edit', 'page_id', $found);
        $this->translations(ArticleTranslation::query()->published()->lazyById(200), 'admin.articles.edit', 'article_id', $found);
        $this->images($found);

        $issues = [];
        foreach (ContentIssueType::cases() as $type) {
            array_push($issues, ...($found[$type->value] ?? []));
        }

        return new ContentQualityData(
            issues: array_slice($issues, 0, self::LIMIT),
            total: count($issues),
        );
    }

    /**
     * @param  iterable<int, PageTranslation|ArticleTranslation>  $translations
     * @param  array<string, list<ContentIssueData>>  $found
     */
    private function translations(iterable $translations, string $editRoute, string $parentKey, array &$found): void
    {
        $titles = [];

        foreach ($translations as $translation) {
            $editUrl = route($editRoute, (int) $translation->getAttribute($parentKey));
            $add = function (ContentIssueType $type, ?string $detail = null) use (&$found, $translation, $editUrl, $parentKey): void {
                $found[$type->value][] = new ContentIssueData(
                    id: implode(':', [$type->value, $parentKey, $translation->id, md5((string) $detail)]),
                    type: $type,
                    label: $translation->title,
                    locale: $translation->locale,
                    editUrl: $editUrl,
                    detail: $detail,
                );
            };

            if (blank($translation->meta_description)) {
                $add(ContentIssueType::MissingDescription);
            }

            if ($this->isEmpty($translation)) {
                $add(ContentIssueType::EmptyContent);
            }

            foreach (array_unique($this->hrefs($translation->body ?? [])) as $href) {
                if ($this->links->isBroken($href)) {
                    $add(ContentIssueType::BrokenLink, Str::limit($href, 200));
                }
            }

            $key = $translation->locale.'|'.Str::lower(trim($translation->title));
            if (isset($titles[$key])) {
                $add(ContentIssueType::DuplicateTitle);
            }
            $titles[$key] = true;
        }
    }

    private function isEmpty(PageTranslation|ArticleTranslation $translation): bool
    {
        if ($translation instanceof PageTranslation && $translation->page->layout_mode === 'sections') {
            return ! PageSection::query()
                ->where('page_id', $translation->page_id)
                ->where('locale', $translation->locale)
                ->where('enabled', true)
                ->exists();
        }

        return RichTextRenderer::summary($translation->body) === null;
    }

    /**
     * @param  array<array-key, mixed>  $node  Tiptap document or one of its nodes.
     * @return list<string>
     */
    private function hrefs(array $node): array
    {
        $found = [];

        foreach ($node as $key => $value) {
            if ($key === 'href' && is_string($value)) {
                $found[] = $value;
            } elseif (is_array($value)) {
                array_push($found, ...$this->hrefs($value));
            }
        }

        return $found;
    }

    /**
     * @param  array<string, list<ContentIssueData>>  $found
     */
    private function images(array &$found): void
    {
        $images = MediaAsset::query()
            ->where('status', MediaStatus::Clean)
            ->whereIn('mime', MediaAsset::IMAGE_MIMES)
            ->where(fn ($query) => $query->whereNull('alt')->orWhere('alt', ''))
            ->lazyById(200);

        foreach ($images as $image) {
            $found[ContentIssueType::MissingAltText->value][] = new ContentIssueData(
                id: 'media:'.$image->id,
                type: ContentIssueType::MissingAltText,
                label: $image->original_name,
                locale: null,
                editUrl: route('admin.media.edit', $image),
            );
        }
    }
}
