<?php

namespace App\Actions\ContactForms;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\ContactForm;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a contact form and replace its fields in the given
 * order, in one transaction with an audit entry (keys and types only, no
 * labels). Stored messages keep their values; a removed field is shown by
 * its key afterwards.
 */
class SaveContactForm
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  array{key: string, enabled: bool, title: array<string, string>, fields: list<array<string, mixed>>}  $values
     *
     * @throws ValidationException When the form changed in the meantime (`conflict`).
     */
    public function handle(?ContactForm $form, array $values, User $actor, ?string $expectedUpdatedAt = null): ContactForm
    {
        return DB::transaction(function () use ($form, $values, $actor, $expectedUpdatedAt): ContactForm {
            $isNew = $form === null;

            if ($form !== null) {
                $form = ContactForm::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
                $expected = $expectedUpdatedAt === null ? null : Carbon::parse($expectedUpdatedAt)->getTimestamp();

                if ($form->updated_at?->getTimestamp() !== $expected) {
                    throw ValidationException::withMessages(['conflict' => __('admin.contactForms.conflict')]);
                }
            }

            $form ??= new ContactForm;
            $previousKey = $isNew ? null : $form->key;
            $before = $isNew ? null : $form->fields()->pluck('type', 'key')->map(fn ($type) => $type->value)->all();

            $form->fill(['key' => $values['key'], 'title' => $values['title'], 'enabled' => $values['enabled']]);
            // Always bumps updated_at, so a change of the fields alone is a new version.
            $form->touch();

            $form->fields()->delete();
            foreach ($values['fields'] as $position => $field) {
                $form->fields()->create([...$field, 'position' => $position]);
            }

            $after = array_column(array_map(fn (array $field): array => ['key' => $field['key'], 'type' => $field['type']->value], $values['fields']), 'type', 'key');

            $this->audit->handle($isNew ? AuditAction::ContactFormCreated : AuditAction::ContactFormUpdated, $form, $actor, [
                'key' => RecordAuditEvent::change($previousKey, $form->key),
                'enabled' => RecordAuditEvent::change(null, $form->enabled),
                'fields' => RecordAuditEvent::change($before, $after),
            ]);

            return $form;
        });
    }

    public function delete(ContactForm $form, User $actor): void
    {
        DB::transaction(function () use ($form, $actor): void {
            $this->audit->handle(AuditAction::ContactFormDeleted, $form, $actor, [
                'key' => RecordAuditEvent::change($form->key, null),
            ]);

            $form->delete();
        });
    }
}
