import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Alert,
    ConflictDialog,
    PageHeader,
    ResourceForm,
    Stack,
    type ResourceFormField,
    type ResourceFormSection,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { edit, index, update } from '@/routes/admin/home-sections';
import {
    MAX_ARTICLES_LIMIT,
    MAX_FAQ_LIMIT,
    MAX_FEATURES,
    MAX_TESTIMONIALS,
    NONE,
    toContent,
    toFieldErrors,
    toFormValues,
    type SectionFormValues,
} from './section-form-values';

type EditorProps = App.Data.Admin.HomeSections.HomeSectionEditorData;
type Field = ResourceFormField<SectionFormValues>;

const ICONS: App.Enums.HomeIcon[] = [
    'palette',
    'lock',
    'zap',
    'accessibility',
    'shield-check',
    'rocket',
    'sparkles',
    'globe',
];

export type SectionFormProps = {
    editor: EditorProps;
};

/**
 * Content form of one home section: fields of its type composed with
 * `ResourceForm` (repeaters for features and testimonials), optimistic-lock
 * conflict handling and an unsaved-changes guard.
 */
export function SectionForm({ editor }: SectionFormProps) {
    const { section, linkTargets, pages } = editor;
    const { t } = useTranslation();
    const form = useForm<SectionFormValues>(toFormValues(section));
    const [isConflictOpen, setIsConflictOpen] = useState(false);
    const typeLabel = t(`admin.homeSections.types.${section.type}`);

    const errors = toFieldErrors(
        form.errors as Partial<Record<string, string>>,
    );
    const generalErrors = Object.entries(errors)
        .filter(([key]) => key === 'content' || key === 'updated_at')
        .map(([, message]) => message ?? '');

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

            if (!window.confirm(t('admin.homeSections.unsavedChanges'))) {
                event.preventDefault();
            }
        });
    }, [form.isDirty, t]);

    function submit() {
        form.transform((data) => ({
            updated_at: section.updatedAt ?? '',
            content: toContent(section.type, data),
        }));

        form.submit(update(section.id), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: (received: Partial<Record<string, string>>) => {
                if (received.conflict) {
                    setIsConflictOpen(true);
                }
            },
        });
    }

    function reloadLatest() {
        setIsConflictOpen(false);
        router.visit(edit.url(section.id), { preserveState: false });
    }

    const text = (name: 'eyebrow' | 'title', required = false): Field => ({
        type: 'text',
        name,
        label: t(`admin.homeSections.fields.${name}`),
        required,
    });

    const description: Field = {
        type: 'textarea',
        name: 'description',
        label: t('admin.homeSections.fields.description'),
        rows: 3,
    };

    const actionFields = (prefix: 'primary' | 'secondary'): Field[] => {
        const action = t(`admin.homeSections.fields.${prefix}Action`);
        const target = form.data[`${prefix}Target`];

        return [
            {
                type: 'select',
                name: `${prefix}Target`,
                label: t('admin.homeSections.fields.actionTarget', { action }),
                options: [
                    {
                        value: NONE,
                        label: t('admin.homeSections.fields.targetNone'),
                    },
                    ...linkTargets.map((value) => ({
                        value,
                        label: t(`admin.homeSections.targets.${value}`),
                    })),
                ],
            },
            ...(target === NONE
                ? []
                : [
                      {
                          type: 'text',
                          name: `${prefix}Label`,
                          label: t('admin.homeSections.fields.actionLabel', {
                              action,
                          }),
                          required: true,
                      } satisfies Field,
                  ]),
            ...(target === 'page'
                ? [
                      {
                          type: 'select',
                          name: `${prefix}PageId`,
                          label: t('admin.homeSections.fields.actionPage', {
                              action,
                          }),
                          placeholder: t(
                              'admin.homeSections.fields.pagePlaceholder',
                          ),
                          required: true,
                          options: pages.map((page) => ({
                              value: String(page.id),
                              label: page.title,
                          })),
                      } satisfies Field,
                  ]
                : []),
        ];
    };

    const limit = (max: number, hint: string, required: boolean): Field => ({
        type: 'number',
        name: 'limit',
        label: t('admin.homeSections.fields.limit'),
        hint,
        min: 1,
        max,
        step: 1,
        required,
    });

    function contentFields(): Field[] {
        switch (section.type) {
            case 'hero':
                return [text('eyebrow'), text('title', true), description];
            case 'cta':
            case 'contact':
                return [text('title', true), description];
            case 'features':
                return [
                    text('title'),
                    description,
                    {
                        type: 'repeater',
                        name: 'items',
                        label: t('admin.homeSections.fields.features'),
                        maxItems: MAX_FEATURES,
                        newItem: () => ({
                            icon: NONE,
                            title: '',
                            description: '',
                        }),
                        labels: {
                            add: t('admin.homeSections.fields.featureAdd'),
                            item: (position) =>
                                t('admin.homeSections.fields.featureItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'select',
                                name: 'icon',
                                label: t('admin.homeSections.fields.icon'),
                                options: [
                                    {
                                        value: NONE,
                                        label: t(
                                            'admin.homeSections.fields.iconNone',
                                        ),
                                    },
                                    ...ICONS.map((icon) => ({
                                        value: icon,
                                        label: t(
                                            `admin.homeSections.icons.${icon}`,
                                        ),
                                    })),
                                ],
                            },
                            {
                                type: 'text',
                                name: 'title',
                                label: t('admin.homeSections.fields.itemTitle'),
                                required: true,
                            },
                            {
                                type: 'textarea',
                                name: 'description',
                                label: t(
                                    'admin.homeSections.fields.itemDescription',
                                ),
                                rows: 2,
                                required: true,
                            },
                        ],
                    },
                ];
            case 'testimonials':
                return [
                    text('title'),
                    {
                        type: 'repeater',
                        name: 'items',
                        label: t('admin.homeSections.fields.testimonials'),
                        maxItems: MAX_TESTIMONIALS,
                        newItem: () => ({ author: '', role: '', quote: '' }),
                        labels: {
                            add: t('admin.homeSections.fields.testimonialAdd'),
                            item: (position) =>
                                t('admin.homeSections.fields.testimonialItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'textarea',
                                name: 'quote',
                                label: t('admin.homeSections.fields.quote'),
                                rows: 3,
                                required: true,
                            },
                            {
                                type: 'text',
                                name: 'author',
                                label: t('admin.homeSections.fields.author'),
                                required: true,
                            },
                            {
                                type: 'text',
                                name: 'role',
                                label: t('admin.homeSections.fields.role'),
                            },
                        ],
                    },
                ];
            case 'faq':
                return [
                    text('title'),
                    description,
                    limit(
                        MAX_FAQ_LIMIT,
                        t('admin.homeSections.fields.faqLimitHint'),
                        false,
                    ),
                ];
            case 'latest_articles':
                return [
                    text('title'),
                    limit(
                        MAX_ARTICLES_LIMIT,
                        t('admin.homeSections.fields.articlesLimitHint'),
                        true,
                    ),
                ];
        }
    }

    const hasActions = section.type === 'hero' || section.type === 'cta';
    const sections: ResourceFormSection<SectionFormValues>[] = [
        {
            id: 'content',
            title: t('admin.homeSections.contentTitle'),
            fields: contentFields(),
        },
        ...(hasActions
            ? [
                  {
                      id: 'actions',
                      title: t('admin.homeSections.actionsTitle'),
                      fields: [
                          ...actionFields('primary'),
                          ...actionFields('secondary'),
                      ],
                  },
              ]
            : []),
    ];

    return (
        <Stack gap="relaxed">
            <PageHeader
                title={t('admin.homeSections.editTitle', {
                    section: typeLabel,
                })}
                description={t('admin.homeSections.editDescription', {
                    locale: editor.locale.native,
                })}
            />

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.homeSections.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            <ResourceForm<SectionFormValues>
                values={form.data}
                errors={errors}
                onChange={(name, value) =>
                    form.setData((data) => ({ ...data, [name]: value }))
                }
                onSubmit={submit}
                isPending={form.processing}
                cancelHref={index({ query: { locale: section.locale } })}
                labels={{
                    submit: form.processing
                        ? t('admin.homeSections.saving')
                        : t('admin.homeSections.save'),
                    cancel: t('admin.homeSections.cancel'),
                    errorSummaryTitle: t(
                        'admin.homeSections.errorSummaryTitle',
                    ),
                }}
                sections={sections}
            />

            <ConflictDialog
                open={isConflictOpen}
                onOpenChange={setIsConflictOpen}
                title={t('admin.homeSections.conflictTitle')}
                description={t('admin.homeSections.conflictDescription')}
                reloadLabel={t('admin.homeSections.conflictReload')}
                onReload={reloadLatest}
                overwriteLabel={t('admin.homeSections.cancel')}
                onOverwrite={() => setIsConflictOpen(false)}
            />
        </Stack>
    );
}
