<?php

namespace App\Actions\Tags;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create, rename or delete a tag in one transaction with an audit entry
 * (slug only). Deleting a tag removes it from every article.
 */
class SaveTag
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array{slug: string, name: array<string, string>}  $values
     *
     * @throws ValidationException When the tag changed in the meantime (`conflict`).
     */
    public function handle(?Tag $tag, array $values, User $actor, ?string $expectedUpdatedAt = null): Tag
    {
        return DB::transaction(function () use ($tag, $values, $actor, $expectedUpdatedAt): Tag {
            $isNew = $tag === null;

            if ($tag !== null) {
                $tag = Tag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
                $expected = $expectedUpdatedAt === null ? null : Carbon::parse($expectedUpdatedAt)->getTimestamp();

                if ($tag->updated_at?->getTimestamp() !== $expected) {
                    throw ValidationException::withMessages(['conflict' => __('admin.tags.conflict')]);
                }
            }

            $tag ??= new Tag;
            $previousSlug = $isNew ? null : $tag->slug;

            $tag->fill($values);
            // Always bumps updated_at, so a rename alone is a new version.
            $tag->touch();

            $this->audit->handle($isNew ? AuditAction::TagCreated : AuditAction::TagUpdated, $tag, $actor, [
                'slug' => RecordAuditEvent::change($previousSlug, $tag->slug),
            ]);

            return $tag;
        });
    }

    public function delete(Tag $tag, User $actor): void
    {
        DB::transaction(function () use ($tag, $actor): void {
            $this->audit->handle(AuditAction::TagDeleted, $tag, $actor, [
                'slug' => RecordAuditEvent::change($tag->slug, null),
            ]);

            $tag->delete();
        });
    }
}
