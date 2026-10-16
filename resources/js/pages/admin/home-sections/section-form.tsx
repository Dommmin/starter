import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Alert,
    ConflictDialog,
    PageHeader,
    ResourceForm,
    Stack,
    type ImagePickerFieldLabels,
    type ResourceFormField,
    type ResourceFormSection,
} from '@/design-system/primitives';
import { useMediaImagePicker } from '@/hooks/use-media-image-picker';
import { useTranslation } from '@/i18n';
import { edit, index, update } from '@/routes/admin/home-sections';
import {
    MAX_ARTICLES_LIMIT,
    MAX_FAQ_LIMIT,
    MAX_FEATURES,
    MAX_HERO_STATS,
    MAX_TESTIMONIAL_STATS,
    CURRENCIES,
    HIGHLIGHT_NO,
    HIGHLIGHT_YES,
    MAX_MEMBERS,
    MAX_PLANS,
    MAX_PROJECTS,
    MAX_TESTIMONIALS,
    NONE,
    PRICE_EXACT,
    PRICE_FROM,
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
    const imagePicker = useMediaImagePicker();
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

    const imageLabels: ImagePickerFieldLabels = {
        choose: t('admin.homeSections.image.choose'),
        change: t('admin.homeSections.image.change'),
        remove: t('admin.homeSections.image.remove'),
        empty: t('admin.homeSections.image.empty'),
        preview: t('admin.homeSections.image.preview'),
    };
    const picker = {
        ...imagePicker,
        labels: {
            ...imagePicker.labels,
            dialogTitle: t('admin.homeSections.image.choose'),
            submit: t('admin.homeSections.image.choose'),
        },
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
                return [
                    text('eyebrow'),
                    text('title', true),
                    description,
                    {
                        type: 'image',
                        name: 'imageId',
                        label: t('admin.homeSections.fields.image'),
                        hint: t('admin.homeSections.fields.heroImageHint'),
                        picker,
                        labels: imageLabels,
                    },
                    {
                        type: 'repeater',
                        name: 'stats',
                        label: t('admin.homeSections.fields.stats'),
                        maxItems: MAX_HERO_STATS,
                        newItem: () => ({ value: '', label: '' }),
                        labels: {
                            add: t('admin.homeSections.fields.statAdd'),
                            item: (position) =>
                                t('admin.homeSections.fields.statItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'text',
                                name: 'value',
                                label: t('admin.homeSections.fields.statValue'),
                                hint: t(
                                    'admin.homeSections.fields.statValueHint',
                                ),
                                required: true,
                            },
                            {
                                type: 'text',
                                name: 'label',
                                label: t('admin.homeSections.fields.statLabel'),
                                required: true,
                            },
                        ],
                    },
                ];
            case 'cta':
                return [text('title', true), description];
            case 'contact':
                return [
                    text('title', true),
                    description,
                    {
                        type: 'select',
                        name: 'formKey',
                        label: t('admin.homeSections.fields.contactForm'),
                        hint: t('admin.homeSections.fields.contactFormHelp'),
                        options: [
                            {
                                value: NONE,
                                label: t(
                                    'admin.homeSections.fields.contactFormDefault',
                                ),
                            },
                            ...editor.contactForms.map((option) => ({
                                value: option.key,
                                label: option.title,
                            })),
                        ],
                    },
                ];
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
                        name: 'stats',
                        label: t('admin.homeSections.fields.stats'),
                        maxItems: MAX_TESTIMONIAL_STATS,
                        newItem: () => ({ value: '', label: '' }),
                        labels: {
                            add: t('admin.homeSections.fields.statAdd'),
                            item: (position) =>
                                t('admin.homeSections.fields.statItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'text',
                                name: 'value',
                                label: t('admin.homeSections.fields.statValue'),
                                hint: t(
                                    'admin.homeSections.fields.statValueHint',
                                ),
                                required: true,
                            },
                            {
                                type: 'text',
                                name: 'label',
                                label: t('admin.homeSections.fields.statLabel'),
                                required: true,
                            },
                        ],
                    },
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
            case 'portfolio':
                return [
                    text('title'),
                    description,
                    {
                        type: 'repeater',
                        name: 'items',
                        label: t('admin.homeSections.fields.projects'),
                        maxItems: MAX_PROJECTS,
                        newItem: () => ({
                            imageId: '',
                            title: '',
                            category: '',
                            description: '',
                            url: '',
                        }),
                        labels: {
                            add: t('admin.homeSections.fields.projectAdd'),
                            item: (position) =>
                                t('admin.homeSections.fields.projectItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'text',
                                name: 'title',
                                label: t('admin.homeSections.fields.itemTitle'),
                                required: true,
                            },
                            {
                                type: 'image',
                                name: 'imageId',
                                label: t('admin.homeSections.fields.thumbnail'),
                                picker,
                                labels: imageLabels,
                            },
                            {
                                type: 'text',
                                name: 'category',
                                label: t('admin.homeSections.fields.category'),
                            },
                            {
                                type: 'textarea',
                                name: 'description',
                                label: t(
                                    'admin.homeSections.fields.itemDescription',
                                ),
                                rows: 2,
                            },
                            {
                                type: 'text',
                                name: 'url',
                                label: t(
                                    'admin.homeSections.fields.projectUrl',
                                ),
                                hint: t(
                                    'admin.homeSections.fields.projectUrlHint',
                                ),
                            },
                        ],
                    },
                ];
            case 'team':
                return [
                    text('title'),
                    description,
                    {
                        type: 'repeater',
                        name: 'items',
                        label: t('admin.homeSections.fields.members'),
                        maxItems: MAX_MEMBERS,
                        newItem: () => ({
                            photoId: '',
                            name: '',
                            role: '',
                            bio: '',
                            skills: '',
                        }),
                        labels: {
                            add: t('admin.homeSections.fields.memberAdd'),
                            item: (position) =>
                                t('admin.homeSections.fields.memberItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'text',
                                name: 'name',
                                label: t('admin.homeSections.fields.name'),
                                required: true,
                            },
                            {
                                type: 'text',
                                name: 'role',
                                label: t(
                                    'admin.homeSections.fields.memberRole',
                                ),
                                required: true,
                            },
                            {
                                type: 'image',
                                name: 'photoId',
                                label: t('admin.homeSections.fields.photo'),
                                picker,
                                labels: imageLabels,
                            },
                            {
                                type: 'textarea',
                                name: 'bio',
                                label: t('admin.homeSections.fields.bio'),
                                rows: 3,
                            },
                            {
                                type: 'text',
                                name: 'skills',
                                label: t('admin.homeSections.fields.skills'),
                                hint: t('admin.homeSections.fields.skillsHint'),
                            },
                        ],
                    },
                ];
            case 'pricing':
                return [
                    text('title'),
                    description,
                    {
                        type: 'repeater',
                        name: 'items',
                        label: t('admin.homeSections.fields.plans'),
                        maxItems: MAX_PLANS,
                        newItem: () => ({
                            name: '',
                            price: '',
                            currency: CURRENCIES[0],
                            priceMode: PRICE_EXACT,
                            period: '',
                            description: '',
                            features: '',
                            highlighted: HIGHLIGHT_NO,
                            actionTarget: NONE,
                            actionLabel: '',
                            actionPageId: '',
                        }),
                        labels: {
                            add: t('admin.homeSections.fields.planAdd'),
                            item: (position) =>
                                t('admin.homeSections.fields.planItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'text',
                                name: 'name',
                                label: t('admin.homeSections.fields.planName'),
                                required: true,
                            },
                            {
                                type: 'text',
                                name: 'price',
                                label: t('admin.homeSections.fields.price'),
                                hint: t('admin.homeSections.fields.priceHint'),
                            },
                            {
                                type: 'select',
                                name: 'currency',
                                label: t('admin.homeSections.fields.currency'),
                                required: true,
                                options: CURRENCIES.map((currency) => ({
                                    value: currency,
                                    label: currency,
                                })),
                            },
                            {
                                type: 'select',
                                name: 'priceMode',
                                label: t('admin.homeSections.fields.priceMode'),
                                required: true,
                                options: [
                                    {
                                        value: PRICE_EXACT,
                                        label: t(
                                            'admin.homeSections.fields.priceExact',
                                        ),
                                    },
                                    {
                                        value: PRICE_FROM,
                                        label: t(
                                            'admin.homeSections.fields.priceStarting',
                                        ),
                                    },
                                ],
                            },
                            {
                                type: 'text',
                                name: 'period',
                                label: t('admin.homeSections.fields.period'),
                                hint: t('admin.homeSections.fields.periodHint'),
                            },
                            {
                                type: 'textarea',
                                name: 'description',
                                label: t(
                                    'admin.homeSections.fields.itemDescription',
                                ),
                                rows: 2,
                            },
                            {
                                type: 'textarea',
                                name: 'features',
                                label: t(
                                    'admin.homeSections.fields.planFeatures',
                                ),
                                hint: t(
                                    'admin.homeSections.fields.planFeaturesHint',
                                ),
                                rows: 4,
                            },
                            {
                                type: 'select',
                                name: 'highlighted',
                                label: t(
                                    'admin.homeSections.fields.highlighted',
                                ),
                                required: true,
                                options: [
                                    {
                                        value: HIGHLIGHT_NO,
                                        label: t(
                                            'admin.homeSections.fields.highlightedNo',
                                        ),
                                    },
                                    {
                                        value: HIGHLIGHT_YES,
                                        label: t(
                                            'admin.homeSections.fields.highlightedYes',
                                        ),
                                    },
                                ],
                            },
                            {
                                type: 'select',
                                name: 'actionTarget',
                                label: t(
                                    'admin.homeSections.fields.planActionTarget',
                                ),
                                options: [
                                    {
                                        value: NONE,
                                        label: t(
                                            'admin.homeSections.fields.targetNone',
                                        ),
                                    },
                                    ...linkTargets.map((value) => ({
                                        value,
                                        label: t(
                                            `admin.homeSections.targets.${value}`,
                                        ),
                                    })),
                                ],
                            },
                            {
                                type: 'text',
                                name: 'actionLabel',
                                label: t(
                                    'admin.homeSections.fields.planActionLabel',
                                ),
                            },
                            {
                                type: 'select',
                                name: 'actionPageId',
                                label: t(
                                    'admin.homeSections.fields.planActionPage',
                                ),
                                hint: t(
                                    'admin.homeSections.fields.planActionPageHint',
                                ),
                                placeholder: t(
                                    'admin.homeSections.fields.pagePlaceholder',
                                ),
                                options: pages.map((page) => ({
                                    value: String(page.id),
                                    label: page.title,
                                })),
                            },
                        ],
                    },
                    {
                        type: 'text',
                        name: 'note',
                        label: t('admin.homeSections.fields.note'),
                    },
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
                readOnly={!editor.can.update}
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
