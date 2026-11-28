<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\ContentPreviewLink;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create a share link of one saved translation. The returned URL is the only
 * place the token ever appears: the database keeps its hash.
 */
class CreateContentPreviewLink
{
    /** Lifetimes offered in the editor, in days. */
    public const array LIFETIMES = [1, 7, 30];

    public const int MAX_ACTIVE = 10;

    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @throws ValidationException When the record already has too many active links.
     */
    public function handle(Page|Article $record, string $locale, int $days, User $actor): string
    {
        return DB::transaction(function () use ($record, $locale, $days, $actor): string {
            ContentPreviewLink::query()->where('expires_at', '<=', now())->delete();

            $active = ContentPreviewLink::query()
                ->where('subject_type', $record->getMorphClass())
                ->where('subject_id', $record->id)
                ->count();

            if ($active >= self::MAX_ACTIVE) {
                throw ValidationException::withMessages([
                    'preview_link' => __('admin.previewLinks.tooMany', ['limit' => self::MAX_ACTIVE]),
                ]);
            }

            $token = Str::random(48);

            ContentPreviewLink::query()->create([
                'subject_type' => $record->getMorphClass(),
                'subject_id' => $record->id,
                'locale' => $locale,
                'token_hash' => ContentPreviewLink::hashToken($token),
                'created_by' => $actor->id,
                'expires_at' => now()->addDays($days),
            ]);

            $this->audit->handle(
                $record instanceof Page ? AuditAction::PagePreviewShared : AuditAction::ArticlePreviewShared,
                $record,
                $actor,
                [
                    'locale' => RecordAuditEvent::change(null, $locale),
                    'days' => RecordAuditEvent::change(null, $days),
                ],
            );

            return route('content.shared-preview', $token);
        });
    }
}
