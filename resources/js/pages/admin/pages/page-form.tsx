import type { FormDataConvertible } from '@inertiajs/core';
import { router, useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Alert,
    Button,
    ConfirmDialog,
    ConflictDialog,
    PageHeader,
    ResourceForm,
    Stack,
    Tabs,
    Text,
    type RichTextDocument,
    type RichTextFieldLabels,
} from '@/design-system/primitives';
import { useMediaImagePicker } from '@/hooks/use-media-image-picker';
import { useTranslation } from '@/i18n';
import { slugify } from '@/lib/slug';
import {
    destroy as pagesDestroy,
    edit as pagesEdit,
    index as pagesIndex,
    store as pagesStore,
    update as pagesUpdate,
} from '@/routes/admin/pages';

type EditorData = App.Data.Admin.Pages.PageEditorData;
type TranslationFormData = App.Data.Admin.Pages.PageTranslationFormData;

/** Local, editable state of one language version (snake_case = request keys). */
type TranslationValues = {
    title: string;
    slug: string;
    meta_description: string;
    body: RichTextDocument;
    status: App.Enums.PublicationStatus;
};

/**
 * Request-ready form state. The rich text body is kept serialised because
 * Inertia form data must be JSON-convertible, while Tiptap node attributes
 * are open (`unknown`).
 */
type TranslationState = Omit<TranslationValues, 'body'> & { body: string };

type PageFormState = {
    translations: Record<string, TranslationState>;
};

const TRANSLATION_FIELDS = [
    'title',
    'slug',
    'meta_description',
    'body',
    'status',
] as const;

const EMPTY_DOCUMENT: RichTextDocument = { type: 'doc', content: [] };

function parseDocument(serialized: string): RichTextDocument {
    const parsed: unknown = JSON.parse(serialized);

    return typeof parsed === 'object' &&
        parsed !== null &&
        'type' in parsed &&
        parsed.type === 'doc'
        ? (parsed as RichTextDocument)
        : EMPTY_DOCUMENT;
}

/** A document with nothing but empty paragraphs is stored as "no body". */
function isEmptyDocument(document: RichTextDocument): boolean {
    return (document.content ?? []).every(
        (node) =>
            node.type === 'paragraph' && (node.content ?? []).length === 0,
    );
}

function toState(
    translation: TranslationFormData | undefined,
): TranslationState {
    return {
        title: translation?.title ?? '',
        slug: translation?.slug ?? '',
        meta_description: translation?.metaDescription ?? '',
        body: JSON.stringify(translation?.body ?? EMPTY_DOCUMENT),
        status: translation?.status ?? 'draft',
    };
}

function errorPrefix(locale: string): string {
    return `translations.${locale}.`;
}

export type PageFormProps = {
    editor: EditorData;
};

/**
 * Shared create/edit screen of a content page: one tab per active public
 * locale (each a `ResourceForm` submitting all languages at once), auto-slug,
 * optimistic-lock conflict handling and deletion.
 */
export function PageForm({ editor }: PageFormProps) {
    const { page, locales, can } = editor;
    const pageId = page.id;
    const { t } = useTranslation();

    const form = useForm<PageFormState>({
        translations: Object.fromEntries(
            locales.available.map((locale) => [
                locale.code,
                toState(page.translations[locale.code]),
            ]),
        ),
    });

    const [activeLocale, setActiveLocale] = useState(locales.default);
    const previewUrl: string | undefined = editor.previewUrls[activeLocale];
    const activeLanguage =
        locales.available.find((locale) => locale.code === activeLocale)
            ?.native ?? activeLocale;
    const [manualSlugs, setManualSlugs] = useState<Record<string, boolean>>(
        () =>
            Object.fromEntries(
                locales.available.map((locale) => [
                    locale.code,
                    (page.translations[locale.code]?.slug ?? '') !== '',
                ]),
            ),
    );
    const [isConflictOpen, setIsConflictOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const errors = form.errors as Partial<Record<string, string>>;
    const errorKeys = Object.keys(errors).filter((key) => errors[key]);

    const localeHasErrors = (code: string) =>
        errorKeys.some((key) => key.startsWith(errorPrefix(code)));

    const firstLocaleWithErrors = (keys: string[]) =>
        locales.available.find((locale) =>
            keys.some((key) => key.startsWith(errorPrefix(locale.code))),
        )?.code;

    /** Errors that no field renders inline (e.g. `updated_at`, `translations.de`). */
    const generalErrors = errorKeys
        .filter(
            (key) =>
                key !== 'conflict' &&
                !locales.available.some((locale) =>
                    TRANSLATION_FIELDS.some(
                        (field) =>
                            key === `${errorPrefix(locale.code)}${field}`,
                    ),
                ),
        )
        .map((key) => errors[key] as string);

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

            if (!window.confirm(t('admin.pages.unsavedChanges'))) {
                event.preventDefault();
            }
        });
    }, [form.isDirty, t]);

    function updateTranslation(
        code: string,
        changes: Partial<TranslationState>,
    ) {
        form.setData('translations', {
            ...form.data.translations,
            [code]: { ...form.data.translations[code], ...changes },
        });
    }

    function handleChange<Key extends keyof TranslationValues>(
        code: string,
        name: Key,
        value: TranslationValues[Key],
    ) {
        if (name === 'title' && !manualSlugs[code]) {
            updateTranslation(code, {
                title: value as string,
                slug: slugify(value as string),
            });

            return;
        }

        if (name === 'slug') {
            setManualSlugs((current) => ({
                ...current,
                [code]: value !== '',
            }));
        }

        updateTranslation(
            code,
            name === 'body'
                ? { body: JSON.stringify(value) }
                : { [name]: value },
        );
    }

    function submit() {
        form.transform((data) => ({
            translations: Object.fromEntries(
                Object.entries(data.translations).map(([code, state]) => {
                    const body: FormDataConvertible = JSON.parse(state.body);

                    return [
                        code,
                        {
                            ...state,
                            body: isEmptyDocument(parseDocument(state.body))
                                ? null
                                : body,
                        },
                    ];
                }),
            ),
            ...(pageId !== null ? { updated_at: page.updatedAt ?? '' } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: (received: Partial<Record<string, string>>) => {
                if (received.conflict) {
                    setIsConflictOpen(true);

                    return;
                }

                const keys = Object.keys(received);
                const target = localeHasErrorsIn(keys, activeLocale)
                    ? activeLocale
                    : firstLocaleWithErrors(keys);

                if (target) {
                    setActiveLocale(target);
                }
            },
        };

        if (pageId === null) {
            form.submit(pagesStore(), options);
        } else {
            form.submit(pagesUpdate(pageId), options);
        }
    }

    function reloadLatest() {
        if (pageId === null) {
            return;
        }

        setIsConflictOpen(false);
        router.visit(pagesEdit.url(pageId), { preserveState: false });
    }

    function confirmDelete() {
        if (pageId === null) {
            return;
        }

        setIsDeleting(true);
        router.delete(pagesDestroy.url(pageId), {
            onFinish: () => setIsDeleting(false),
        });
    }

    const imagePicker = useMediaImagePicker();
    const richTextLabels: RichTextFieldLabels = {
        toolbar: t('admin.richText.toolbar'),
        heading2: t('admin.richText.heading2'),
        heading3: t('admin.richText.heading3'),
        heading4: t('admin.richText.heading4'),
        bold: t('admin.richText.bold'),
        italic: t('admin.richText.italic'),
        strike: t('admin.richText.strike'),
        code: t('admin.richText.code'),
        bulletList: t('admin.richText.bulletList'),
        orderedList: t('admin.richText.orderedList'),
        blockquote: t('admin.richText.blockquote'),
        horizontalRule: t('admin.richText.horizontalRule'),
        link: t('admin.richText.link'),
        unlink: t('admin.richText.unlink'),
        linkDialogTitle: t('admin.richText.linkDialogTitle'),
        linkUrlLabel: t('admin.richText.linkUrlLabel'),
        linkUrlHint: t('admin.richText.linkUrlHint'),
        linkSubmit: t('admin.richText.linkSubmit'),
        linkCancel: t('admin.richText.linkCancel'),
        linkClose: t('admin.richText.linkClose'),
        linkInvalid: t('admin.richText.linkInvalid'),
    };

    function localeState(code: string): string {
        if (localeHasErrors(code)) {
            return t('admin.pages.localeHasErrors');
        }

        if (code === locales.default) {
            return t('admin.pages.localeRequired');
        }

        return form.data.translations[code]?.title.trim()
            ? t('admin.pages.localeOptional')
            : t('admin.pages.localeMissing');
    }

    const otherLocalesWithErrors = locales.available
        .filter(
            (locale) =>
                locale.code !== activeLocale && localeHasErrors(locale.code),
        )
        .map((locale) => locale.native);

    return (
        <Stack gap="relaxed">
            <PageHeader
                title={
                    pageId === null
                        ? t('admin.pages.createTitle')
                        : t('admin.pages.editTitle')
                }
                description={t('admin.pages.description')}
                actions={
                    pageId !== null ? (
                        <>
                            {previewUrl ? (
                                <Button
                                    variant="outline"
                                    href={previewUrl}
                                    external
                                >
                                    <ExternalLink aria-hidden="true" />
                                    {t('admin.pages.preview', {
                                        language: activeLanguage,
                                    })}
                                </Button>
                            ) : (
                                <Button variant="outline" disabled>
                                    <ExternalLink aria-hidden="true" />
                                    {t('admin.pages.preview', {
                                        language: activeLanguage,
                                    })}
                                </Button>
                            )}
                            {can.delete && (
                                <Button
                                    variant="destructive"
                                    onClick={() => setIsDeleteOpen(true)}
                                >
                                    {t('admin.pages.delete')}
                                </Button>
                            )}
                        </>
                    ) : undefined
                }
            />

            {pageId !== null && (
                <Text variant="caption" tone="muted">
                    {!previewUrl
                        ? t('admin.pages.previewUnavailable')
                        : form.isDirty
                          ? t('admin.pages.previewUnsaved')
                          : t('admin.pages.previewHint')}
                </Text>
            )}

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.pages.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            {otherLocalesWithErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.pages.errorsInOtherLanguages', {
                        languages: otherLocalesWithErrors.join(', '),
                    })}
                />
            )}

            <Tabs
                ariaLabel={t('admin.pages.fields.locale')}
                value={activeLocale}
                onValueChange={setActiveLocale}
                items={locales.available.map((locale) => {
                    const code = locale.code;
                    const state = form.data.translations[code];
                    const values: TranslationValues = {
                        ...state,
                        body: parseDocument(state.body),
                    };
                    const isDefault = code === locales.default;
                    const prefix = errorPrefix(code);

                    return {
                        value: code,
                        label: t('admin.pages.localeTabLabel', {
                            language: locale.native,
                            state: localeState(code),
                        }),
                        content: (
                            <ResourceForm<TranslationValues>
                                values={values}
                                errors={Object.fromEntries(
                                    TRANSLATION_FIELDS.map((field) => [
                                        field,
                                        errors[`${prefix}${field}`],
                                    ]),
                                )}
                                onChange={(name, value) =>
                                    handleChange(code, name, value)
                                }
                                onSubmit={submit}
                                isPending={form.processing}
                                cancelHref={pagesIndex()}
                                labels={{
                                    submit: form.processing
                                        ? t('admin.pages.saving')
                                        : t('admin.pages.save'),
                                    cancel: t('admin.pages.cancel'),
                                    errorSummaryTitle: t(
                                        'admin.pages.errorSummaryTitle',
                                    ),
                                }}
                                sections={[
                                    {
                                        id: `translation-${code}`,
                                        title: locale.native,
                                        description: localeState(code),
                                        fields: [
                                            {
                                                type: 'text',
                                                name: 'title',
                                                label: t(
                                                    'admin.pages.fields.title',
                                                ),
                                                hint: t(
                                                    'admin.pages.fields.titleHelp',
                                                ),
                                                required: isDefault,
                                            },
                                            {
                                                type: 'text',
                                                name: 'slug',
                                                label: t(
                                                    'admin.pages.fields.slug',
                                                ),
                                                hint: t(
                                                    'admin.pages.fields.slugHelp',
                                                ),
                                                required: isDefault,
                                            },
                                            {
                                                type: 'textarea',
                                                name: 'meta_description',
                                                label: t(
                                                    'admin.pages.fields.metaDescription',
                                                ),
                                                hint: `${t('admin.pages.fields.metaDescriptionHelp')} ${t(
                                                    'admin.pages.fields.metaDescriptionCounter',
                                                    {
                                                        length: values
                                                            .meta_description
                                                            .length,
                                                    },
                                                )}`,
                                                rows: 3,
                                            },
                                            {
                                                type: 'richText',
                                                name: 'body',
                                                label: t(
                                                    'admin.pages.fields.body',
                                                ),
                                                labels: richTextLabels,
                                                imagePicker,
                                            },
                                            {
                                                type: 'select',
                                                name: 'status',
                                                label: t(
                                                    'admin.pages.fields.status',
                                                ),
                                                options: [
                                                    {
                                                        value: 'draft',
                                                        label: t(
                                                            'admin.pages.status.draft',
                                                        ),
                                                    },
                                                    {
                                                        value: 'published',
                                                        label: t(
                                                            'admin.pages.status.published',
                                                        ),
                                                        disabled: !can.publish,
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ]}
                            />
                        ),
                    };
                })}
            />

            {pageId !== null && (
                <ConflictDialog
                    open={isConflictOpen}
                    onOpenChange={setIsConflictOpen}
                    title={t('admin.pages.conflictTitle')}
                    description={t('admin.pages.conflictDescription')}
                    reloadLabel={t('admin.pages.conflictReload')}
                    onReload={reloadLatest}
                    overwriteLabel={t('admin.pages.cancel')}
                    onOverwrite={() => setIsConflictOpen(false)}
                />
            )}

            {pageId !== null && can.delete && (
                <ConfirmDialog
                    open={isDeleteOpen}
                    onOpenChange={setIsDeleteOpen}
                    title={t('admin.pages.deleteTitle')}
                    description={t('admin.pages.deleteDescription')}
                    confirmLabel={t('admin.pages.deleteConfirm')}
                    cancelLabel={t('admin.pages.cancel')}
                    closeLabel={t('actions.close')}
                    tone="destructive"
                    isPending={isDeleting}
                    onConfirm={confirmDelete}
                />
            )}
        </Stack>
    );
}

function localeHasErrorsIn(keys: string[], code: string): boolean {
    return keys.some((key) => key.startsWith(errorPrefix(code)));
}
