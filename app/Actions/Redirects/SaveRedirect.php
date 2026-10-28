<?php

namespace App\Actions\Redirects;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create, update or delete a redirect in one transaction with an audit
 * entry (source, target, status and state; the hit counter is not audited).
 */
class SaveRedirect
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array{source_path: string, target: string, status_code: int, is_active: bool}  $values
     *
     * @throws ValidationException When the redirect changed in the meantime (`conflict`).
     */
    public function handle(?Redirect $redirect, array $values, User $actor, ?string $expectedUpdatedAt = null): Redirect
    {
        return DB::transaction(function () use ($redirect, $values, $actor, $expectedUpdatedAt): Redirect {
            $isNew = $redirect === null;

            if ($redirect !== null) {
                $redirect = Redirect::query()->whereKey($redirect->id)->lockForUpdate()->firstOrFail();
                $expected = $expectedUpdatedAt === null ? null : Carbon::parse($expectedUpdatedAt)->getTimestamp();

                if ($redirect->updated_at?->getTimestamp() !== $expected) {
                    throw ValidationException::withMessages(['conflict' => __('admin.redirects.conflict')]);
                }
            }

            $redirect ??= new Redirect;
            $previous = $isNew ? [] : $this->auditedValues($redirect);

            $redirect->fill($values);
            // Always bumps updated_at, so every save is a new version.
            $redirect->touch();

            $changes = [];
            foreach ($this->auditedValues($redirect) as $field => $value) {
                if ($isNew || $previous[$field] !== $value) {
                    $changes[$field] = RecordAuditEvent::change($previous[$field] ?? null, $value);
                }
            }

            $this->audit->handle($isNew ? AuditAction::RedirectCreated : AuditAction::RedirectUpdated, $redirect, $actor, $changes);

            return $redirect;
        });
    }

    /**
     * Delete the given redirects (one audit entry each); ids that no longer
     * exist are skipped, so a repeated request is harmless.
     *
     * @param  list<int>  $ids
     * @param  (callable(Redirect): mixed)|null  $authorize  Called for every record before any is deleted.
     * @return int Number of deleted redirects.
     */
    public function delete(array $ids, User $actor, ?callable $authorize = null): int
    {
        return DB::transaction(function () use ($ids, $actor, $authorize): int {
            $redirects = Redirect::query()->whereKey($ids)->lockForUpdate()->get();

            if ($authorize !== null) {
                $redirects->each($authorize);
            }

            foreach ($redirects as $redirect) {
                $this->audit->handle(AuditAction::RedirectDeleted, $redirect, $actor, [
                    'source_path' => RecordAuditEvent::change($redirect->source_path, null),
                ]);

                $redirect->delete();
            }

            return $redirects->count();
        });
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function auditedValues(Redirect $redirect): array
    {
        return [
            'source_path' => $redirect->source_path,
            'target' => $redirect->target,
            'status_code' => $redirect->status_code->value,
            'is_active' => $redirect->is_active,
        ];
    }
}
