<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\HomeSectionType;
use App\Models\Article;
use App\Models\ContentBlock;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\ContentSnapshots;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Data;

/**
 * Bring back the texts of an earlier revision: titles, descriptions, bodies
 * and, for pages, the layout and sections.
 *
 * Slugs, publication status and dates never change, so restoring cannot
 * publish anything or break an address. A language that no longer has a
 * translation is skipped. The replaced content is stored first, so a restore
 * can be undone.
 */
class RestoreContentRevision
{
    public function __construct(
        private readonly ContentSnapshots $snapshots,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Page|Article $record, ContentRevision $revision, User $actor): void
    {
        DB::transaction(function () use ($record, $revision, $actor): void {
            $locked = $record::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            $this->snapshots->record($locked, $actor->id);

            /** @var array<string, array<string, mixed>> $stored */
            $stored = $revision->snapshot['translations'] ?? [];
            $restored = [];

            foreach ($locked->translations()->get() as $translation) {
                if (! isset($stored[$translation->locale])) {
                    continue;
                }

                $translation->fill($stored[$translation->locale])->save();
                $restored[] = $translation->locale;
            }

            if ($locked instanceof Page) {
                $this->restoreSections($locked, $revision->snapshot);
            }

            $locked->forceFill([
                'updated_by' => $actor->id,
                'updated_at' => $locked->freshTimestamp(),
            ])->save();

            $this->audit->handle(
                $locked instanceof Page ? AuditAction::PageRevisionRestored : AuditAction::ArticleRevisionRestored,
                $locked,
                $actor,
                [
                    'revision' => RecordAuditEvent::change(null, $revision->id),
                    'locales' => RecordAuditEvent::change(null, $restored),
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function restoreSections(Page $page, array $snapshot): void
    {
        if (is_string($snapshot['layout_mode'] ?? null)) {
            $page->layout_mode = $snapshot['layout_mode'];
        }

        $page->sections()->delete();

        /** @var list<array{content_block_id?: int|null, locale: string, type: string, position: int, enabled: bool, content: array<string, mixed>}> $sections */
        $sections = $snapshot['sections'] ?? [];

        foreach ($sections as $section) {
            $type = HomeSectionType::from($section['type']);

            $content = $type->contentClass()::from($section['content']);

            $page->sections()->create([
                'content_block_id' => $this->linkableBlock($section['content_block_id'] ?? null, $content),
                'locale' => $section['locale'],
                'type' => $type,
                'position' => $section['position'],
                'enabled' => $section['enabled'],
                'content' => $content,
            ]);
        }
    }

    /**
     * A restored section follows its block again only while their content is
     * equal; otherwise the next block edit would overwrite the restored text.
     */
    private function linkableBlock(?int $blockId, Data $content): ?int
    {
        $block = $blockId === null ? null : ContentBlock::query()->find($blockId);

        return $block !== null && $block->content->toArray() == $content->toArray() ? $block->id : null;
    }
}
