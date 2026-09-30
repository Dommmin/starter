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
    destroy as articlesDestroy,
    edit as articlesEdit,
    index as articlesIndex,
    store as articlesStore,
    update as articlesUpdate,
} from '@/routes/admin/articles';

type EditorData = App.Data.Admin.Articles.ArticleEditorData;
type TranslationFormData = App.Data.Admin.Articles.ArticleTranslationFormData;

/** Local, editable state of one language version (snake_case = request keys). */
type TranslationValues = {
    title: string;
    slug: string;
    excerpt: string;
    meta_description: string;
    body: RichTextDocument;
    status: App.Enums.PublicationStatus;
    published_on: string;
};

/** Values of one language tab: its translation plus the shared cover. */
type TabValues = TranslationValues & { cover_media_id: string };

/**
 * Request-ready form state. The rich text body is kept serialised because
 * Inertia form data must be JSON-convertible, while Tiptap node attributes
 * are open (`unknown`).
 */
type TranslationState = Omit<TranslationValues, 'body'> & { body: string };

type ArticleFormState = {
    cover_media_id: string;
    translations: Record<string, TranslationState>;
};

const TRANSLATION_FIELDS = [
    'title',
    'slug',
    'excerpt',
    'meta_description',
    'body',
    'status',
    'published_on',
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
        excerpt: translation?.excerpt ?? '',
        meta_description: translation?.metaDescription ?? '',
        body: JSON.stringify(translation?.body ?? EMPTY_DOCUMENT),
        status: translation?.status ?? 'draft',
        published_on: translation?.publishedOn ?? '',
    };
}

function errorPrefix(locale: string): string {
    return `translations.${locale}.`;
}

export type ArticleFormProps = {
    editor: EditorData;
};

/**
 * Shared create/edit screen of an article: one tab per active public locale
 * (each a `ResourceForm` submitting all languages and the shared cover at
 * once), auto-slug, optimistic-lock conflict handling and deletion.
 */
export function ArticleForm({ editor }: ArticleFormProps) {
    const { article, locales, can } = editor;
    const articleId = article.id;
    const { t } = useTranslation();

    const form = useForm<ArticleFormState>({
        cover_media_id:
            article.coverMediaId === null ? '' : String(article.coverMediaId),
        translations: Object.fromEntries(
            locales.available.map((locale) => [
                locale.code,
                toState(article.translations[locale.code]),
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
                    (article.translations[locale.code]?.slug ?? '') !== '',
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
                key !== 'cover_media_id' &&
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

            if (!window.confirm(t('admin.articles.unsavedChanges'))) {
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

    function handleChange<Key extends keyof TabValues>(
        code: string,
        name: Key,
        value: TabValues[Key],
    ) {
        if (name === 'cover_media_id') {
            form.setData('cover_media_id', value as string);

            return;
        }

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
            cover_media_id:
                data.cover_media_id === '' ? null : Number(data.cover_media_id),
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
            ...(articleId !== null
                ? { updated_at: article.updatedAt ?? '' }
                : {}),
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

        if (articleId === null) {
            form.submit(articlesStore(), options);
        } else {
            form.submit(articlesUpdate(articleId), options);
        }
    }

    function reloadLatest() {
        if (articleId === null) {
            return;
        }

        setIsConflictOpen(false);
        router.visit(articlesEdit.url(articleId), { preserveState: false });
    }

    function confirmDelete() {
        if (articleId === null) {
            return;
        }

        setIsDeleting(true);
        router.delete(articlesDestroy.url(articleId), {
            onFinish: () => setIsDeleting(false),
        });
    }

    const imagePicker = useMediaImagePicker();
    const coverPicker = {
        ...imagePicker,
        labels: {
            ...imagePicker.labels,
            dialogTitle: t('admin.articles.cover.choose'),
            submit: t('admin.articles.cover.choose'),
        },
    };
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
            return t('admin.articles.localeHasErrors');
        }

        if (code === locales.default) {
            return t('admin.articles.localeRequired');
        }

        return form.data.translations[code]?.title.trim()
            ? t('admin.articles.localeOptional')
            : t('admin.articles.localeMissing');
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
                    articleId === null
                        ? t('admin.articles.createTitle')
                        : t('admin.articles.editTitle')
                }
                description={t('admin.articles.description')}
                actions={
                    articleId !== null ? (
                        <>
                            {previewUrl ? (
                                <Button
                                    variant="outline"
                                    href={previewUrl}
                                    external
                                >
                                    <ExternalLink aria-hidden="true" />
                                    {t('admin.articles.preview', {
                                        language: activeLanguage,
                                    })}
                                </Button>
                            ) : (
                                <Button variant="outline" disabled>
                                    <ExternalLink aria-hidden="true" />
                                    {t('admin.articles.preview', {
                                        language: activeLanguage,
                                    })}
                                </Button>
                            )}
                            {can.delete && (
                                <Button
                                    variant="destructive"
                                    onClick={() => setIsDeleteOpen(true)}
                                >
                                    {t('admin.articles.delete')}
                                </Button>
                            )}
                        </>
                    ) : undefined
                }
            />

            {articleId !== null && (
                <Text variant="caption" tone="muted">
                    {!previewUrl
                        ? t('admin.articles.previewUnavailable')
                        : form.isDirty
                          ? t('admin.articles.previewUnsaved')
                          : t('admin.articles.previewHint')}
                </Text>
            )}

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.articles.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            {otherLocalesWithErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.articles.errorsInOtherLanguages', {
                        languages: otherLocalesWithErrors.join(', '),
                    })}
                />
            )}

            <Tabs
                ariaLabel={t('admin.articles.fields.locale')}
                value={activeLocale}
                onValueChange={setActiveLocale}
                items={locales.available.map((locale) => {
                    const code = locale.code;
                    const state = form.data.translations[code];
                    const values: TabValues = {
                        ...state,
                        body: parseDocument(state.body),
                        cover_media_id: form.data.cover_media_id,
                    };
                    const isDefault = code === locales.default;
                    const prefix = errorPrefix(code);

                    return {
                        value: code,
                        label: t('admin.articles.localeTabLabel', {
                            language: locale.native,
                            state: localeState(code),
                        }),
                        content: (
                            <ResourceForm<TabValues>
                                values={values}
                                errors={{
                                    ...Object.fromEntries(
                                        TRANSLATION_FIELDS.map((field) => [
                                            field,
                                            errors[`${prefix}${field}`],
                                        ]),
                                    ),
                                    cover_media_id: errors.cover_media_id,
                                }}
                                onChange={(name, value) =>
                                    handleChange(code, name, value)
                                }
                                onSubmit={submit}
                                isPending={form.processing}
                                cancelHref={articlesIndex()}
                                labels={{
                                    submit: form.processing
                                        ? t('admin.articles.saving')
                                        : t('admin.articles.save'),
                                    cancel: t('admin.articles.cancel'),
                                    errorSummaryTitle: t(
                                        'admin.articles.errorSummaryTitle',
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
                                                    'admin.articles.fields.title',
                                                ),
                                                hint: isDefault
                                                    ? undefined
                                                    : t(
                                                          'admin.articles.fields.titleHelp',
                                                      ),
                                                required: isDefault,
                                            },
                                            {
                                                type: 'text',
                                                name: 'slug',
                                                label: t(
                                                    'admin.articles.fields.slug',
                                                ),
                                                hint: t(
                                                    'admin.articles.fields.slugHelp',
                                                ),
                                                required: isDefault,
                                            },
                                            {
                                                type: 'textarea',
                                                name: 'excerpt',
                                                label: t(
                                                    'admin.articles.fields.excerpt',
                                                ),
                                                hint: t(
                                                    'admin.articles.fields.excerptHelp',
                                                ),
                                                rows: 3,
                                            },
                                            {
                                                type: 'textarea',
                                                name: 'meta_description',
                                                label: t(
                                                    'admin.articles.fields.metaDescription',
                                                ),
                                                hint: `${t('admin.articles.fields.metaDescriptionHelp')} ${t(
                                                    'admin.articles.fields.metaDescriptionCounter',
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
                                                    'admin.articles.fields.body',
                                                ),
                                                labels: richTextLabels,
                                                imagePicker,
                                            },
                                            {
                                                type: 'select',
                                                name: 'status',
                                                label: t(
                                                    'admin.articles.fields.status',
                                                ),
                                                options: [
                                                    {
                                                        value: 'draft',
                                                        label: t(
                                                            'admin.articles.status.draft',
                                                        ),
                                                    },
                                                    {
                                                        value: 'published',
                                                        label: t(
                                                            'admin.articles.status.published',
                                                        ),
                                                        disabled: !can.publish,
                                                    },
                                                ],
                                            },
                                            {
                                                type: 'date',
                                                name: 'published_on',
                                                label: t(
                                                    'admin.articles.fields.publishedOn',
                                                ),
                                                hint: t(
                                                    'admin.articles.fields.publishedOnHelp',
                                                ),
                                            },
                                        ],
                                    },
                                    {
                                        id: `cover-${code}`,
                                        title: t('admin.articles.coverSection'),
                                        description: t(
                                            'admin.articles.coverSectionDescription',
                                        ),
                                        fields: [
                                            {
                                                type: 'image',
                                                name: 'cover_media_id',
                                                label: t(
                                                    'admin.articles.fields.cover',
                                                ),
                                                hint: t(
                                                    'admin.articles.fields.coverHelp',
                                                ),
                                                picker: coverPicker,
                                                labels: {
                                                    choose: t(
                                                        'admin.articles.cover.choose',
                                                    ),
                                                    change: t(
                                                        'admin.articles.cover.change',
                                                    ),
                                                    remove: t(
                                                        'admin.articles.cover.remove',
                                                    ),
                                                    empty: t(
                                                        'admin.articles.cover.empty',
                                                    ),
                                                    preview: t(
                                                        'admin.articles.cover.preview',
                                                    ),
                                                },
                                            },
                                        ],
                                    },
                                ]}
                            />
                        ),
                    };
                })}
            />

            {articleId !== null && (
                <ConflictDialog
                    open={isConflictOpen}
                    onOpenChange={setIsConflictOpen}
                    title={t('admin.articles.conflictTitle')}
                    description={t('admin.articles.conflictDescription')}
                    reloadLabel={t('admin.articles.conflictReload')}
                    onReload={reloadLatest}
                    overwriteLabel={t('admin.articles.cancel')}
                    onOverwrite={() => setIsConflictOpen(false)}
                />
            )}

            {articleId !== null && can.delete && (
                <ConfirmDialog
                    open={isDeleteOpen}
                    onOpenChange={setIsDeleteOpen}
                    title={t('admin.articles.deleteTitle')}
                    description={t('admin.articles.deleteDescription')}
                    confirmLabel={t('admin.articles.deleteConfirm')}
                    cancelLabel={t('admin.articles.cancel')}
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
