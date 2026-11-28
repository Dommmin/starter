<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\ContentBlock;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\Services\Content\ContentSnapshots;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

/**
 * Every change of shared blocks: share a section, insert or detach a copy,
 * update all copies and delete an unused block. Each page that changes gets
 * a revision first.
 */
class ContentBlocks
{
    public function __construct(
        private readonly ContentSnapshots $snapshots,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function share(Page $page, PageSection $section, string $name, User $actor): ContentBlock
    {
        return DB::transaction(function () use ($page, $section, $name, $actor): ContentBlock {
            $this->snapshots->record($page, $actor->id);

            $block = ContentBlock::query()->create([
                'name' => $name,
                'locale' => $section->locale,
                'type' => $section->type,
                'content' => $section->content,
            ]);
            $section->forceFill(['content_block_id' => $block->id])->save();

            $this->audit->handle(AuditAction::ContentBlockCreated, $block, $actor, ['name' => RecordAuditEvent::change(null, $name)]);

            return $block;
        });
    }

    public function insert(Page $page, ContentBlock $block, User $actor): PageSection
    {
        return DB::transaction(function () use ($page, $block, $actor): PageSection {
            $this->snapshots->record($page, $actor->id);

            return $page->sections()->create([
                'content_block_id' => $block->id,
                'locale' => $block->locale,
                'type' => $block->type,
                'position' => ((int) $page->sections()->where('locale', $block->locale)->max('position')) + 1,
                'enabled' => true,
                'content' => $block->content,
            ]);
        });
    }

    /**
     * The section keeps its current content and stops following the block.
     */
    public function detach(Page $page, PageSection $section, User $actor): void
    {
        DB::transaction(function () use ($page, $section, $actor): void {
            $this->snapshots->record($page, $actor->id);
            $section->forceFill(['content_block_id' => null])->save();
        });
    }

    /**
     * Store new content in the block and in every linked section. Must run
     * inside the caller's transaction.
     */
    public function update(int $blockId, Data $content, User $actor): void
    {
        $block = ContentBlock::query()->whereKey($blockId)->lockForUpdate()->firstOrFail();
        $sections = $block->sections()->with('page')->orderBy('id')->lockForUpdate()->get();

        foreach ($sections->pluck('page')->filter()->unique('id') as $page) {
            $this->snapshots->record($page, $actor->id);
        }

        $block->forceFill(['content' => $content])->save();

        foreach ($sections as $section) {
            $section->forceFill(['content' => $content])->save();
        }

        $this->audit->handle(AuditAction::ContentBlockUpdated, $block, $actor, [
            'sections' => RecordAuditEvent::change(null, $sections->count()),
        ]);
    }

    /**
     * @throws ValidationException While a section still follows the block.
     */
    public function delete(ContentBlock $block, User $actor): void
    {
        DB::transaction(function () use ($block, $actor): void {
            $locked = ContentBlock::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();

            if ($locked->sections()->exists()) {
                throw ValidationException::withMessages(['block' => __('admin.pages.blocks.inUse')]);
            }

            $this->audit->handle(AuditAction::ContentBlockDeleted, $locked, $actor, ['name' => RecordAuditEvent::change($locked->name, null)]);
            $locked->delete();
        });
    }
}
