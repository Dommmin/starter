<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps the history of page and article content. Callers record a snapshot
 * inside their transaction, before they change anything.
 */
class ContentSnapshots
{
    /** Newest revisions kept for each record. */
    public const int KEEP = 20;

    /**
     * Store the current content, unless it equals the newest stored one.
     */
    public function record(Page|Article $record, ?int $authorId): void
    {
        $snapshot = $this->capture($record);
        $latest = $this->query($record)->orderByDesc('id')->first();

        if ($latest !== null && $latest->snapshot == $snapshot) {
            return;
        }

        ContentRevision::query()->create([
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->id,
            'author_id' => $authorId,
            'snapshot' => $snapshot,
        ]);

        $oldestKept = $this->query($record)->orderByDesc('id')->skip(self::KEEP - 1)->value('id');

        if (is_numeric($oldestKept)) {
            $this->query($record)->where('id', '<', (int) $oldestKept)->delete();
        }
    }

    /**
     * Remove the history of a record that is deleted for good.
     */
    public function forget(Page|Article $record): void
    {
        $this->query($record)->delete();
    }

    /**
     * @return Builder<ContentRevision>
     */
    public function query(Page|Article $record): Builder
    {
        return ContentRevision::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function capture(Page|Article $record): array
    {
        $translations = [];
        foreach ($record->translations()->orderBy('locale')->get() as $translation) {
            $translations[$translation->locale] = [
                'title' => $translation->title,
                'meta_description' => $translation->meta_description,
                'body' => $translation->body,
                ...($record instanceof Article ? ['excerpt' => $translation->getAttribute('excerpt')] : []),
            ];
        }

        if ($record instanceof Article) {
            return ['translations' => $translations];
        }

        return [
            'translations' => $translations,
            'layout_mode' => (string) Page::query()->whereKey($record->id)->value('layout_mode'),
            'sections' => $record->sections()->orderBy('locale')->orderBy('position')->orderBy('id')->get()
                ->map(static fn (PageSection $section): array => [
                    'content_block_id' => $section->content_block_id,
                    'locale' => $section->locale,
                    'type' => $section->type->value,
                    'position' => $section->position,
                    'enabled' => $section->enabled,
                    'content' => $section->content->toArray(),
                ])
                ->values()
                ->all(),
        ];
    }
}
