<?php

namespace App\Actions\Home;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\HomeSection;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * Replace the content of a home section with optimistic locking on
 * `updated_at`, auditing `home_section.updated` in the same transaction.
 * The audit entry lists the changed top-level content fields only (the
 * texts themselves are not copied into the log).
 */
class UpdateHomeSection
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  Data  $content  Instance of the section type's content class.
     * @param  string  $expectedUpdatedAt  Version the form was loaded with.
     *
     * @throws ValidationException When the section changed in the meantime (`conflict`).
     */
    public function handle(HomeSection $section, User $actor, Data $content, string $expectedUpdatedAt): HomeSection
    {
        $class = $section->type->contentClass();

        if (! $content instanceof $class) {
            throw new InvalidArgumentException("Content of a [{$section->type->value}] section must be [{$class}].");
        }

        return DB::transaction(function () use ($section, $actor, $content, $expectedUpdatedAt): HomeSection {
            $locked = HomeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.homeSections.conflict'),
                ]);
            }

            $before = $locked->content->toArray();
            $after = $content->toArray();

            $changes = [];
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
                if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                    $changes["content.{$field}"] = RecordAuditEvent::redacted();
                }
            }

            $locked->content = $content;
            $locked->forceFill(['updated_at' => $locked->freshTimestamp()])->save();

            $this->audit->handle(AuditAction::HomeSectionUpdated, $locked, $actor, $changes);

            return $locked;
        });
    }
}
