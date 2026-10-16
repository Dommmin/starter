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
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { slugify } from '@/lib/slug';
import { destroy, edit, index, store, update } from '@/routes/admin/tags';

type EditorProps = App.Data.Admin.Tags.TagEditorData;

/** Flat editor state: the slug and `name_{locale}` per language. */
type FormValues = Record<`name_${string}`, string> & { slug: string };

export type TagEditorProps = {
    editor: EditorProps;
};

/**
 * Shared create/edit screen of a tag. A new tag takes its slug from the
 * name in the default language until the slug is edited by hand.
 */
export function TagEditor({ editor }: TagEditorProps) {
    const record = editor.tag;
    const recordId = record.id;
    const locales = editor.locales.available.map((locale) => locale.code);
    const defaultLocale = editor.locales.default;
    const { t } = useTranslation();
    const form = useForm<FormValues>({
        slug: record.slug,
        ...Object.fromEntries(
            locales.map((locale) => [
                `name_${locale}`,
                record.name[locale] ?? '',
            ]),
        ),
    } as FormValues);
    const [isSlugManual, setIsSlugManual] = useState(recordId !== null);
    const [isConflictOpen, setIsConflictOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    // The request uses `name.pl`; the flat editor reads `name_pl`.
    const errors: Partial<Record<string, string>> = {};
    for (const [key, message] of Object.entries(
        form.errors as Partial<Record<string, string>>,
    )) {
        errors[key.replace(/^name\.(\w+)$/, 'name_$1')] = message;
    }
    const generalErrors = Object.entries(errors)
        .filter(([key]) => key !== 'conflict' && !/^(slug|name_\w+)$/.test(key))
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

            if (!window.confirm(t('admin.tags.unsavedChanges'))) {
                event.preventDefault();
            }
        });
    }, [form.isDirty, t]);

    function change(name: string, value: string) {
        form.setData((data) => {
            const next = { ...data, [name]: value } as FormValues;

            if (name === `name_${defaultLocale}` && !isSlugManual) {
                next.slug = slugify(value).slice(0, 60);
            }

            return next;
        });

        if (name === 'slug') {
            setIsSlugManual(value !== '');
        }
    }

    function submit() {
        form.transform((data) => ({
            slug: data.slug,
            name: Object.fromEntries(
                locales.map((locale) => [locale, data[`name_${locale}`]]),
            ),
            ...(recordId !== null ? { updated_at: record.updatedAt } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: (received: Partial<Record<string, string>>) => {
                if (received.conflict) {
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

    return (
        <Stack gap="relaxed">
            <PageHeader
                title={
                    recordId === null
                        ? t('admin.tags.createTitle')
                        : t('admin.tags.editTitle')
                }
                description={t('admin.tags.editorDescription')}
                actions={
                    recordId !== null && editor.canDelete ? (
                        <Button
                            variant="destructive"
                            onClick={() => setIsDeleteOpen(true)}
                        >
                            {t('admin.tags.delete')}
                        </Button>
                    ) : undefined
                }
            />

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.tags.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            <ResourceForm<FormValues>
                values={form.data}
                errors={errors}
                onChange={(name, value) => change(name, value)}
                onSubmit={submit}
                readOnly={!editor.canUpdate}
                isPending={form.processing}
                cancelHref={index()}
                labels={{
                    submit: form.processing
                        ? t('admin.tags.saving')
                        : t('admin.tags.save'),
                    cancel: t('admin.tags.cancel'),
                    errorSummaryTitle: t('admin.tags.errorSummaryTitle'),
                }}
                sections={[
                    {
                        id: 'details',
                        title: t('admin.tags.detailsTitle'),
                        fields: [
                            ...locales.map((locale) => ({
                                type: 'text' as const,
                                name: `name_${locale}` as const,
                                label: t('admin.tags.fields.name', {
                                    language: languageLabel(locale),
                                }),
                                required: locale === defaultLocale,
                            })),
                            {
                                type: 'text',
                                name: 'slug',
                                label: t('admin.tags.fields.slug'),
                                hint: t('admin.tags.fields.slugHelp'),
                                required: true,
                            },
                        ],
                    },
                ]}
            />

            {recordId !== null && (
                <ConflictDialog
                    open={isConflictOpen}
                    onOpenChange={setIsConflictOpen}
                    title={t('admin.tags.conflictTitle')}
                    description={t('admin.tags.conflict')}
                    reloadLabel={t('admin.tags.conflictReload')}
                    onReload={reloadLatest}
                    overwriteLabel={t('admin.tags.cancel')}
                    onOverwrite={() => setIsConflictOpen(false)}
                />
            )}

            {recordId !== null && editor.canDelete && (
                <ConfirmDialog
                    open={isDeleteOpen}
                    onOpenChange={setIsDeleteOpen}
                    title={t('admin.tags.deleteTitle')}
                    description={t('admin.tags.deleteDescription')}
                    confirmLabel={t('admin.tags.deleteConfirm')}
                    cancelLabel={t('admin.tags.cancel')}
                    closeLabel={t('actions.close')}
                    tone="destructive"
                    isPending={isDeleting}
                    onConfirm={confirmDelete}
                />
            )}
        </Stack>
    );
}
