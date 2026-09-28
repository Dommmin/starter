import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Alert,
    ConflictDialog,
    PageHeader,
    ResourceForm,
    Stack,
    Tabs,
    type ImagePickerFieldLabels,
    type ResourceFormSection,
} from '@/design-system/primitives';
import { useMediaImagePicker } from '@/hooks/use-media-image-picker';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import {
    edit as siteSettingsEdit,
    update as siteSettingsUpdate,
} from '@/routes/admin/site-settings';

type EditorData = App.Data.Admin.Settings.SiteSettingsEditorData;
type SocialNetwork = App.Enums.SocialNetwork;

/** Request keys of the settings shared by every language. */
const SHARED_FIELDS = [
    'site_name',
    'logo_media_id',
    'og_image_media_id',
    'contact_email',
    'contact_phone',
    'address_line',
    'postal_code',
    'city',
    'country_code',
    'contact_recipient_email',
] as const;

/** Request keys of the texts of one language (`translations.{locale}.*`). */
const TRANSLATION_FIELDS = [
    'tagline',
    'footer_text',
    'seo_title',
    'seo_description',
] as const;

type SharedField = (typeof SHARED_FIELDS)[number];
type TranslationField = (typeof TRANSLATION_FIELDS)[number];
type TranslationState = Record<TranslationField, string>;

type SiteSettingsFormState = Record<SharedField, string> & {
    social_links: Record<SocialNetwork, string>;
    translations: Record<string, TranslationState>;
};

/**
 * Values of one language tab: the shared settings, one `social_links.{network}`
 * entry per network and the texts of the tab's language.
 */
type TabValues = Record<string, string>;

function socialField(network: SocialNetwork): string {
    return `social_links.${network}`;
}

function translationPrefix(locale: string): string {
    return `translations.${locale}.`;
}

function toTranslationState(
    translation:
        | App.Data.Admin.Settings.SiteSettingTranslationFormData
        | undefined,
): TranslationState {
    return {
        tagline: translation?.tagline ?? '',
        footer_text: translation?.footerText ?? '',
        seo_title: translation?.seoTitle ?? '',
        seo_description: translation?.seoDescription ?? '',
    };
}

function nullable(value: string): string | null {
    return value.trim() === '' ? null : value;
}

export type SiteSettingsFormProps = {
    editor: EditorData;
};

/**
 * Site settings screen: one tab per active public locale, each a
 * `ResourceForm` with the shared settings (brand, contact, social links,
 * sharing image, contact form recipient) and the texts of that language.
 * Submitting any tab saves everything; a stale version opens the conflict
 * dialog.
 */
export function SiteSettingsForm({ editor }: SiteSettingsFormProps) {
    const { settings, locales, socialNetworks } = editor;
    const { t } = useTranslation();

    const form = useForm<SiteSettingsFormState>({
        site_name: settings.siteName,
        logo_media_id:
            settings.logoMediaId === null ? '' : String(settings.logoMediaId),
        og_image_media_id:
            settings.ogImageMediaId === null
                ? ''
                : String(settings.ogImageMediaId),
        contact_email: settings.contactEmail ?? '',
        contact_phone: settings.contactPhone ?? '',
        address_line: settings.addressLine ?? '',
        postal_code: settings.postalCode ?? '',
        city: settings.city ?? '',
        country_code: settings.countryCode ?? '',
        contact_recipient_email: settings.contactRecipientEmail ?? '',
        social_links: { ...settings.socialLinks },
        translations: Object.fromEntries(
            locales.available.map((locale) => [
                locale.code,
                toTranslationState(settings.translations[locale.code]),
            ]),
        ),
    });

    const [activeLocale, setActiveLocale] = useState(locales.default);
    const [isConflictOpen, setIsConflictOpen] = useState(false);

    const errors = form.errors as Partial<Record<string, string>>;
    const errorKeys = Object.keys(errors).filter((key) => errors[key]);

    const inlineKeys = new Set<string>([
        ...SHARED_FIELDS,
        ...socialNetworks.map((option) => socialField(option.network)),
        ...locales.available.flatMap((locale) =>
            TRANSLATION_FIELDS.map(
                (field) => `${translationPrefix(locale.code)}${field}`,
            ),
        ),
    ]);

    /** Errors that no field renders inline (e.g. `updated_at`, `translations`). */
    const generalErrors = errorKeys
        .filter((key) => key !== 'conflict' && !inlineKeys.has(key))
        .map((key) => errors[key] as string);

    const localeHasErrors = (code: string, keys: string[] = errorKeys) =>
        keys.some((key) => key.startsWith(translationPrefix(code)));

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

            if (!window.confirm(t('admin.siteSettings.unsavedChanges'))) {
                event.preventDefault();
            }
        });
    }, [form.isDirty, t]);

    function handleChange(code: string, name: string, value: string) {
        if (name.startsWith('social_links.')) {
            const network = name.slice('social_links.'.length) as SocialNetwork;

            form.setData('social_links', {
                ...form.data.social_links,
                [network]: value,
            });

            return;
        }

        if ((TRANSLATION_FIELDS as readonly string[]).includes(name)) {
            form.setData('translations', {
                ...form.data.translations,
                [code]: {
                    ...form.data.translations[code],
                    [name]: value,
                },
            });

            return;
        }

        form.setData(name as SharedField, value);
    }

    function submit() {
        form.transform((data) => ({
            updated_at: settings.updatedAt,
            site_name: data.site_name,
            logo_media_id:
                data.logo_media_id === '' ? null : Number(data.logo_media_id),
            og_image_media_id:
                data.og_image_media_id === ''
                    ? null
                    : Number(data.og_image_media_id),
            contact_email: nullable(data.contact_email),
            contact_phone: nullable(data.contact_phone),
            address_line: nullable(data.address_line),
            postal_code: nullable(data.postal_code),
            city: nullable(data.city),
            country_code: nullable(data.country_code),
            contact_recipient_email: nullable(data.contact_recipient_email),
            social_links: data.social_links,
            translations: data.translations,
        }));

        form.submit(siteSettingsUpdate(), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: (received: Partial<Record<string, string>>) => {
                if (received.conflict) {
                    setIsConflictOpen(true);

                    return;
                }

                const keys = Object.keys(received);

                if (localeHasErrors(activeLocale, keys)) {
                    return;
                }

                const target = locales.available.find((locale) =>
                    localeHasErrors(locale.code, keys),
                )?.code;

                if (target) {
                    setActiveLocale(target);
                }
            },
        });
    }

    function reloadLatest() {
        setIsConflictOpen(false);
        router.visit(siteSettingsEdit.url(), { preserveState: false });
    }

    const imagePicker = useMediaImagePicker();
    const imageLabels: ImagePickerFieldLabels = {
        choose: t('admin.siteSettings.image.choose'),
        change: t('admin.siteSettings.image.change'),
        remove: t('admin.siteSettings.image.remove'),
        empty: t('admin.siteSettings.image.empty'),
        preview: t('admin.siteSettings.image.preview'),
    };
    const picker = {
        ...imagePicker,
        labels: {
            ...imagePicker.labels,
            dialogTitle: t('admin.siteSettings.image.choose'),
            submit: t('admin.siteSettings.image.choose'),
        },
    };

    function localeState(code: string): string {
        if (localeHasErrors(code)) {
            return t('admin.siteSettings.localeHasErrors');
        }

        const translation = form.data.translations[code];

        return TRANSLATION_FIELDS.some((field) => translation[field].trim())
            ? t('admin.siteSettings.localeTranslated')
            : t('admin.siteSettings.localeMissing');
    }

    const otherLocalesWithErrors = locales.available
        .filter(
            (locale) =>
                locale.code !== activeLocale && localeHasErrors(locale.code),
        )
        .map((locale) => locale.native);

    function sections(
        locale: App.Data.Content.ContentLocaleData,
        values: TabValues,
    ): ResourceFormSection<TabValues>[] {
        return [
            {
                id: 'general',
                title: t('admin.siteSettings.sections.general.title'),
                description: t(
                    'admin.siteSettings.sections.general.description',
                ),
                fields: [
                    {
                        type: 'text',
                        name: 'site_name',
                        label: t('admin.siteSettings.fields.siteName'),
                        hint: t('admin.siteSettings.fields.siteNameHelp'),
                        required: true,
                        autoComplete: 'organization',
                    },
                    {
                        type: 'image',
                        name: 'logo_media_id',
                        label: t('admin.siteSettings.fields.logo'),
                        hint: t('admin.siteSettings.fields.logoHelp'),
                        picker,
                        labels: imageLabels,
                    },
                ],
            },
            {
                id: 'contact',
                title: t('admin.siteSettings.sections.contact.title'),
                description: t(
                    'admin.siteSettings.sections.contact.description',
                ),
                fields: [
                    {
                        type: 'text',
                        name: 'contact_email',
                        inputType: 'email',
                        autoComplete: 'email',
                        label: t('admin.siteSettings.fields.contactEmail'),
                    },
                    {
                        type: 'text',
                        name: 'contact_phone',
                        inputType: 'tel',
                        autoComplete: 'tel',
                        label: t('admin.siteSettings.fields.contactPhone'),
                        hint: t('admin.siteSettings.fields.contactPhoneHelp'),
                    },
                    {
                        type: 'text',
                        name: 'address_line',
                        autoComplete: 'address-line1',
                        label: t('admin.siteSettings.fields.addressLine'),
                    },
                    {
                        type: 'text',
                        name: 'postal_code',
                        autoComplete: 'postal-code',
                        label: t('admin.siteSettings.fields.postalCode'),
                    },
                    {
                        type: 'text',
                        name: 'city',
                        autoComplete: 'address-level2',
                        label: t('admin.siteSettings.fields.city'),
                    },
                    {
                        type: 'text',
                        name: 'country_code',
                        autoComplete: 'country',
                        label: t('admin.siteSettings.fields.countryCode'),
                        hint: t('admin.siteSettings.fields.countryCodeHelp'),
                    },
                ],
            },
            {
                id: 'social',
                title: t('admin.siteSettings.sections.social.title'),
                description: t(
                    'admin.siteSettings.sections.social.description',
                ),
                fields: socialNetworks.map((option) => ({
                    type: 'text' as const,
                    name: socialField(option.network),
                    inputType: 'url' as const,
                    label: t('admin.siteSettings.fields.socialUrl', {
                        network: option.label,
                    }),
                    hint: t('admin.siteSettings.fields.socialUrlHelp'),
                    placeholder: 'https://',
                })),
            },
            {
                id: 'seo',
                title: t('admin.siteSettings.sections.seo.title'),
                description: t('admin.siteSettings.sections.seo.description'),
                fields: [
                    {
                        type: 'image',
                        name: 'og_image_media_id',
                        label: t('admin.siteSettings.fields.ogImage'),
                        hint: t('admin.siteSettings.fields.ogImageHelp'),
                        picker,
                        labels: imageLabels,
                    },
                ],
            },
            {
                id: `texts-${locale.code}`,
                title: t('admin.siteSettings.sections.texts.title', {
                    language: locale.native,
                }),
                description: t('admin.siteSettings.sections.texts.description'),
                fields: [
                    {
                        type: 'text',
                        name: 'tagline',
                        label: t('admin.siteSettings.fields.tagline'),
                        hint: t('admin.siteSettings.fields.taglineHelp'),
                    },
                    {
                        type: 'textarea',
                        name: 'footer_text',
                        rows: 2,
                        label: t('admin.siteSettings.fields.footerText'),
                        hint: t('admin.siteSettings.fields.footerTextHelp'),
                    },
                    {
                        type: 'text',
                        name: 'seo_title',
                        label: t('admin.siteSettings.fields.seoTitle'),
                        hint: t('admin.siteSettings.fields.seoTitleCounter', {
                            length: values.seo_title.length,
                        }),
                    },
                    {
                        type: 'textarea',
                        name: 'seo_description',
                        rows: 3,
                        label: t('admin.siteSettings.fields.seoDescription'),
                        hint: t(
                            'admin.siteSettings.fields.seoDescriptionCounter',
                            { length: values.seo_description.length },
                        ),
                    },
                ],
            },
            {
                id: 'notifications',
                title: t('admin.siteSettings.sections.notifications.title'),
                description: t(
                    'admin.siteSettings.sections.notifications.description',
                ),
                fields: [
                    {
                        type: 'text',
                        name: 'contact_recipient_email',
                        inputType: 'email',
                        autoComplete: 'off',
                        label: t('admin.siteSettings.fields.recipient'),
                        hint: t('admin.siteSettings.fields.recipientHelp'),
                    },
                ],
            },
        ];
    }

    return (
        <Stack gap="relaxed">
            <PageHeader
                title={t('admin.siteSettings.title')}
                description={t('admin.siteSettings.description')}
            />

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.siteSettings.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            {otherLocalesWithErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.siteSettings.errorsInOtherLanguages', {
                        languages: otherLocalesWithErrors.join(', '),
                    })}
                />
            )}

            <Tabs
                ariaLabel={t('admin.siteSettings.languagesLabel')}
                value={activeLocale}
                onValueChange={setActiveLocale}
                items={locales.available.map((locale) => {
                    const code = locale.code;
                    const prefix = translationPrefix(code);
                    const values: TabValues = {
                        ...Object.fromEntries(
                            SHARED_FIELDS.map((field) => [
                                field,
                                form.data[field],
                            ]),
                        ),
                        ...Object.fromEntries(
                            socialNetworks.map((option) => [
                                socialField(option.network),
                                form.data.social_links[option.network] ?? '',
                            ]),
                        ),
                        ...form.data.translations[code],
                    };

                    return {
                        value: code,
                        label: t('admin.siteSettings.localeTabLabel', {
                            language: locale.native,
                            state: localeState(code),
                        }),
                        content: (
                            <ResourceForm<TabValues>
                                values={values}
                                errors={{
                                    ...errors,
                                    ...Object.fromEntries(
                                        TRANSLATION_FIELDS.map((field) => [
                                            field,
                                            errors[`${prefix}${field}`],
                                        ]),
                                    ),
                                }}
                                onChange={(name, value) =>
                                    handleChange(code, name, value)
                                }
                                onSubmit={submit}
                                isPending={form.processing}
                                recentlySuccessful={form.recentlySuccessful}
                                cancelHref={adminIndex()}
                                labels={{
                                    submit: form.processing
                                        ? t('admin.siteSettings.saving')
                                        : t('admin.siteSettings.save'),
                                    cancel: t('admin.siteSettings.cancel'),
                                    errorSummaryTitle: t(
                                        'admin.siteSettings.errorSummaryTitle',
                                    ),
                                    saved: t('admin.siteSettings.updated'),
                                }}
                                sections={sections(locale, values)}
                            />
                        ),
                    };
                })}
            />

            <ConflictDialog
                open={isConflictOpen}
                onOpenChange={setIsConflictOpen}
                title={t('admin.siteSettings.conflictTitle')}
                description={t('admin.siteSettings.conflictDescription')}
                reloadLabel={t('admin.siteSettings.conflictReload')}
                onReload={reloadLatest}
                overwriteLabel={t('admin.siteSettings.cancel')}
                onOverwrite={() => setIsConflictOpen(false)}
            />
        </Stack>
    );
}
