import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Alert,
    Button,
    ConfirmDialog,
    ConflictDialog,
    PageHeader,
    ResourceForm,
    Stack,
    type ResourceFormRepeaterItem,
    type ResourceFormRepeaterItemField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    destroy,
    edit,
    index,
    store,
    update,
} from '@/routes/admin/contact-forms';

type EditorProps = App.Data.Admin.ContactForms.ContactFormEditorData;
type RecordData = App.Data.Admin.ContactForms.ContactFormFormData;

const FIELD_TYPES: App.Enums.ContactFieldType[] = [
    'text',
    'textarea',
    'select',
    'checkbox',
];
const YES = 'yes';
const NO = 'no';

/**
 * Flat editor state: `title_{locale}` per language and one repeater item
 * per extra field with `label_{locale}` per language.
 */
type FormValues = Record<`title_${string}`, string> & {
    key: string;
    enabled: boolean;
    fields: ResourceFormRepeaterItem[];
};

function toValues(record: RecordData, locales: string[]): FormValues {
    return {
        key: record.key,
        enabled: record.enabled,
        ...Object.fromEntries(
            locales.map((locale) => [
                `title_${locale}`,
                record.title[locale] ?? '',
            ]),
        ),
        fields: record.fields.map((field) => ({
            key: field.key,
            type: field.type,
            required: field.required ? YES : NO,
            options: field.options,
            ...Object.fromEntries(
                locales.map((locale) => [
                    `label_${locale}`,
                    field.label[locale] ?? '',
                ]),
            ),
        })),
    } as FormValues;
}

export type ContactFormEditorProps = {
    editor: EditorProps;
};

/**
 * Shared create/edit screen of a contact form: key, status, title per
 * language and the ordered extra fields. Name, e-mail and message are
 * always part of the form and are not configured here.
 */
export function ContactFormEditor({ editor }: ContactFormEditorProps) {
    const record = editor.form;
    const recordId = record.id;
    const locales = editor.locales.available.map((locale) => locale.code);
    const { t } = useTranslation();
    const form = useForm<FormValues>(toValues(record, locales));
    const [isConflictOpen, setIsConflictOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    // The request uses nested keys (`title.pl`, `fields.0.label.pl`); the
    // flat editor reads `title_pl` and `fields.0.label_pl`.
    const received = form.errors as Partial<Record<string, string>>;
    const errors: Partial<Record<string, string>> = {};
    for (const [key, message] of Object.entries(received)) {
        errors[
            key
                .replace(/^title\.(\w+)$/, 'title_$1')
                .replace(/^(fields\.\d+)\.label\.(\w+)$/, '$1.label_$2')
        ] = message;
    }
    const generalErrors = Object.entries(errors)
        .filter(
            ([key]) =>
                key !== 'conflict' &&
                !/^(key|enabled|title_\w+|fields(\.\d+\.\w+)?)$/.test(key),
        )
        .map(([, message]) => message ?? '')
        .filter((message) => message !== '');

    useEffect(() => {
        if (!form.isDirty) {
            return;
        }

        return router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                visit.method !== 'get' ||
                visit.url.pathname === window.location.pathname
            ) {
                return;
            }

            if (!window.confirm(t('admin.contactForms.unsavedChanges'))) {
                event.preventDefault();
            }
        });
    }, [form.isDirty, t]);

    function submit() {
        form.transform((data) => ({
            key: data.key,
            enabled: data.enabled,
            title: Object.fromEntries(
                locales.map((locale) => [locale, data[`title_${locale}`]]),
            ),
            fields: data.fields.map((field) => ({
                key: field.key ?? '',
                type: field.type ?? 'text',
                required: field.required === YES,
                label: Object.fromEntries(
                    locales.map((locale) => [
                        locale,
                        field[`label_${locale}`] ?? '',
                    ]),
                ),
                options: field.options ?? '',
            })),
            ...(recordId !== null ? { updated_at: record.updatedAt } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: (errorsReceived: Partial<Record<string, string>>) => {
                if (errorsReceived.conflict) {
                    setIsConflictOpen(true);
                }
            },
        };

        if (recordId === null) {
            form.submit(store(), options);
        } else {
            form.submit(update(recordId), options);
        }
    }

    function reloadLatest() {
        if (recordId === null) {
            return;
        }

        setIsConflictOpen(false);
        router.visit(edit.url(recordId), { preserveState: false });
    }

    function confirmDelete() {
        if (recordId === null) {
            return;
        }

        setIsDeleting(true);
        router.delete(destroy.url(recordId), {
            onFinish: () => setIsDeleting(false),
        });
    }

    const languageLabel = (code: string) =>
        editor.locales.available.find((locale) => locale.code === code)
            ?.native ?? code;

    const itemFields: ResourceFormRepeaterItemField[] = [
        {
            type: 'text',
            name: 'key',
            label: t('admin.contactForms.fields.fieldKey'),
            hint: t('admin.contactForms.fields.keyHelp'),
            required: true,
        },
        {
            type: 'select',
            name: 'type',
            label: t('admin.contactForms.fields.type'),
            required: true,
            options: FIELD_TYPES.map((type) => ({
                value: type,
                label: t(`admin.contactForms.types.${type}`),
            })),
        },
        {
            type: 'select',
            name: 'required',
            label: t('admin.contactForms.fields.required'),
            options: [
                { value: NO, label: t('admin.contactForms.optional') },
                { value: YES, label: t('admin.contactForms.mandatory') },
            ],
        },
        ...locales.map((locale) => ({
            type: 'text' as const,
            name: `label_${locale}`,
            label: t('admin.contactForms.fields.label', {
                language: languageLabel(locale),
            }),
            required: locale === editor.locales.default,
        })),
        {
            type: 'textarea',
            name: 'options',
            rows: 4,
            label: t('admin.contactForms.fields.options'),
            hint: t('admin.contactForms.fields.optionsHelp', {
                languages: locales.map(languageLabel).join(' | '),
                max: editor.maxOptions,
            }),
        },
    ];

    return (
        <Stack gap="relaxed">
            <PageHeader
                title={
                    recordId === null
                        ? t('admin.contactForms.createTitle')
                        : t('admin.contactForms.editTitle')
                }
                description={t('admin.contactForms.editorDescription')}
                actions={
                    recordId !== null && editor.canDelete ? (
                        <Button
                            variant="destructive"
                            onClick={() => setIsDeleteOpen(true)}
                        >
                            {t('admin.contactForms.delete')}
                        </Button>
                    ) : undefined
                }
            />

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.contactForms.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            <ResourceForm<FormValues>
                values={form.data}
                errors={errors}
                onChange={(name, value) =>
                    form.setData((data) => ({ ...data, [name]: value }))
                }
                onSubmit={submit}
                isPending={form.processing}
                cancelHref={index()}
                labels={{
                    submit: form.processing
                        ? t('admin.contactForms.saving')
                        : t('admin.contactForms.save'),
                    cancel: t('admin.contactForms.cancel'),
                    errorSummaryTitle: t(
                        'admin.contactForms.errorSummaryTitle',
                    ),
                }}
                sections={[
                    {
                        id: 'details',
                        title: t('admin.contactForms.detailsTitle'),
                        fields: [
                            {
                                type: 'text',
                                name: 'key',
                                label: t('admin.contactForms.fields.key'),
                                hint: t('admin.contactForms.fields.keyHelp'),
                                required: true,
                            },
                            {
                                type: 'switch',
                                name: 'enabled',
                                label: t('admin.contactForms.fields.enabled'),
                            },
                            ...locales.map((locale) => ({
                                type: 'text' as const,
                                name: `title_${locale}` as const,
                                label: t('admin.contactForms.fields.title', {
                                    language: languageLabel(locale),
                                }),
                                required: locale === editor.locales.default,
                            })),
                        ],
                    },
                    {
                        id: 'fields',
                        title: t('admin.contactForms.fieldsTitle'),
                        description: t('admin.contactForms.fieldsDescription'),
                        fields: [
                            {
                                type: 'repeater',
                                name: 'fields',
                                label: t('admin.contactForms.fieldsTitle'),
                                maxItems: editor.maxFields,
                                newItem: () => ({
                                    key: '',
                                    type: 'text',
                                    required: NO,
                                    options: '',
                                    ...Object.fromEntries(
                                        locales.map((locale) => [
                                            `label_${locale}`,
                                            '',
                                        ]),
                                    ),
                                }),
                                labels: {
                                    add: t('admin.contactForms.fieldAdd'),
                                    item: (position) =>
                                        t('admin.contactForms.fieldItem', {
                                            position,
                                        }),
                                },
                                itemFields,
                            },
                        ],
                    },
                ]}
            />

            {recordId !== null && (
                <ConflictDialog
                    open={isConflictOpen}
                    onOpenChange={setIsConflictOpen}
                    title={t('admin.contactForms.conflictTitle')}
                    description={t('admin.contactForms.conflict')}
                    reloadLabel={t('admin.contactForms.conflictReload')}
                    onReload={reloadLatest}
                    overwriteLabel={t('admin.contactForms.cancel')}
                    onOverwrite={() => setIsConflictOpen(false)}
                />
            )}

            {recordId !== null && editor.canDelete && (
                <ConfirmDialog
                    open={isDeleteOpen}
                    onOpenChange={setIsDeleteOpen}
                    title={t('admin.contactForms.deleteTitle')}
                    description={t('admin.contactForms.deleteDescription')}
                    confirmLabel={t('admin.contactForms.deleteConfirm')}
                    cancelLabel={t('admin.contactForms.cancel')}
                    closeLabel={t('actions.close')}
                    tone="destructive"
                    isPending={isDeleting}
                    onConfirm={confirmDelete}
                />
            )}
        </Stack>
    );
}
