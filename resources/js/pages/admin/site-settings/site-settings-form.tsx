import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Alert,
    ConflictDialog,
    Link,
    PageHeader,
    ResourceForm,
    Stack,
    Tabs,
    type ImagePickerFieldLabels,
    type ResourceFormRepeaterItem,
    type ResourceFormSection,
} from '@/design-system/primitives';
import { useMediaImagePicker } from '@/hooks/use-media-image-picker';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as systemIndex } from '@/routes/admin/system';
import {
    edit as siteSettingsEdit,
    update as siteSettingsUpdate,
} from '@/routes/admin/site-settings';
import { SiteSkins } from './site-skins';

type EditorData = App.Data.Admin.Settings.SiteSettingsEditorData;
type SocialNetwork = App.Enums.SocialNetwork;
type Weekday = App.Enums.Weekday;

const WEEKDAYS: Weekday[] = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
];

const ACCENTS: App.Enums.AccentColor[] = [
    'default',
    'blue',
    'violet',
    'rose',
    'green',
];
const TYPOGRAPHY_PRESETS: App.Enums.TypographyPreset[] = ['default', 'classic'];
const THEMES: App.Enums.ThemePreference[] = ['system', 'light', 'dark'];
const ANNOUNCEMENT_TONES: App.Enums.AnnouncementTone[] = ['info', 'warning'];

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
    'accent',
    'typography',
    'default_theme',
    'favicon_media_id',
    'tax_id',
    'map_url',
    'google_site_verification',
    'announcement_tone',
    'announcement_link_url',
    'announcement_starts_on',
    'announcement_ends_on',
] as const;

/** Request keys of the texts of one language (`translations.{locale}.*`). */
const TRANSLATION_FIELDS = [
    'tagline',
    'footer_text',
    'seo_title',
    'seo_description',
    'auto_reply_text',
    'announcement_text',
    'announcement_link_label',
] as const;

type SharedField = (typeof SHARED_FIELDS)[number];
type TranslationField = (typeof TRANSLATION_FIELDS)[number];
type TranslationState = Record<TranslationField, string>;

type OpeningHoursState = Record<Weekday, { opens: string; closes: string }>;

type SiteSettingsFormState = Record<SharedField, string> & {
    social_links: Record<SocialNetwork, string>;
    translations: Record<string, TranslationState>;
    opening_hours: OpeningHoursState;
    contact_recipients: ResourceFormRepeaterItem[];
    contact_auto_reply: boolean;
    announcement_enabled: boolean;
};

/** Contact form recipients the settings accept (UpdateSiteSettingsRequest). */
const MAX_RECIPIENTS = 5;

/**
 * Values of one language tab: the shared settings, one `social_links.{network}`
 * entry per network and the texts of the tab's language.
 */
type TextKey =
    | SharedField
    | TranslationField
    | `social_links.${SocialNetwork}`
    | `opening_hours.${Weekday}.${'opens' | 'closes'}`;

type TabValues = Record<TextKey, string> & {
    contact_recipients: ResourceFormRepeaterItem[];
    contact_auto_reply: boolean;
    announcement_enabled: boolean;
};

function socialField(network: SocialNetwork): `social_links.${SocialNetwork}` {
    return `social_links.${network}`;
}

function hoursField(
    day: Weekday,
    edge: 'opens' | 'closes',
): `opening_hours.${Weekday}.${'opens' | 'closes'}` {
    return `opening_hours.${day}.${edge}`;
}

/** Value of the tab with the settings shared by every language. */
const SHARED_TAB = 'shared';

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
        auto_reply_text: translation?.autoReplyText ?? '',
        announcement_text: translation?.announcementText ?? '',
        announcement_link_label: translation?.announcementLinkLabel ?? '',
    };
}

function nullable(value: string): string | null {
    return value.trim() === '' ? null : value;
}

export type SiteSettingsFormProps = {
    editor: EditorData;
};

/**
 * Site settings screen: a first tab with the settings shared by every
 * language (brand, appearance, contact, social links, sharing image, contact
 * form recipients), then one tab per active public locale with only the texts
 * of that language. Each tab is a `ResourceForm`; submitting any tab saves
 * everything. A stale version opens the conflict dialog.
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
        accent: settings.accent ?? '',
        typography: settings.typography ?? '',
        default_theme: settings.defaultTheme,
        favicon_media_id:
            settings.faviconMediaId === null
                ? ''
                : String(settings.faviconMediaId),
        tax_id: settings.taxId ?? '',
        map_url: settings.mapUrl ?? '',
        google_site_verification: settings.googleSiteVerification ?? '',
        opening_hours: { ...settings.openingHours },
        contact_recipients: settings.contactRecipients.map((email) => ({
            email,
        })),
        contact_auto_reply: settings.contactAutoReply,
        announcement_enabled: settings.announcementEnabled,
        announcement_tone: settings.announcementTone,
        announcement_link_url: settings.announcementLinkUrl ?? '',
        announcement_starts_on: settings.announcementStartsOn ?? '',
        announcement_ends_on: settings.announcementEndsOn ?? '',
        social_links: { ...settings.socialLinks },
        translations: Object.fromEntries(
            locales.available.map((locale) => [
                locale.code,
                toTranslationState(settings.translations[locale.code]),
            ]),
        ),
    });

    const [activeTab, setActiveTab] = useState(SHARED_TAB);
    const [isConflictOpen, setIsConflictOpen] = useState(false);

    const errors = form.errors as Partial<Record<string, string>>;
    const errorKeys = Object.keys(errors).filter((key) => errors[key]);

    const inlineKeys = new Set<string>([
        ...SHARED_FIELDS,
        ...socialNetworks.map((option) => socialField(option.network)),
        ...WEEKDAYS.flatMap((day) => [
            hoursField(day, 'opens'),
            hoursField(day, 'closes'),
        ]),
        ...locales.available.flatMap((locale) =>
            TRANSLATION_FIELDS.map(
                (field) => `${translationPrefix(locale.code)}${field}`,
            ),
        ),
        'contact_recipients',
        'contact_auto_reply',
        'announcement_enabled',
        ...form.data.contact_recipients.map(
            (_, position) => `contact_recipients.${position}`,
        ),
    ]);

    /** Errors that no field renders inline (e.g. `updated_at`, `translations`). */
    const generalErrors = errorKeys
        .filter((key) => key !== 'conflict' && !inlineKeys.has(key))
        .map((key) => errors[key] as string);

    const localeHasErrors = (code: string, keys: string[] = errorKeys) =>
        keys.some((key) => key.startsWith(translationPrefix(code)));

    const sharedHasErrors = (keys: string[] = errorKeys) =>
        keys.some(
            (key) => inlineKeys.has(key) && !key.startsWith('translations.'),
        );

    const tabHasErrors = (tab: string, keys: string[] = errorKeys) =>
        tab === SHARED_TAB ? sharedHasErrors(keys) : localeHasErrors(tab, keys);

    const tabs = [
        SHARED_TAB,
        ...locales.available.map((locale) => locale.code),
    ];

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

    function handleChange(
        code: string,
        name: string,
        value: TabValues[keyof TabValues],
    ) {
        if (name === 'contact_recipients' && Array.isArray(value)) {
            form.setData('contact_recipients', value);

            return;
        }

        if (name === 'contact_auto_reply' && typeof value === 'boolean') {
            form.setData('contact_auto_reply', value);

            return;
        }

        if (name === 'announcement_enabled' && typeof value === 'boolean') {
            form.setData('announcement_enabled', value);

            return;
        }

        if (typeof value !== 'string') {
            return;
        }

        if (name.startsWith('opening_hours.')) {
            const [, day, edge] = name.split('.') as [
                string,
                Weekday,
                'opens' | 'closes',
            ];

            form.setData('opening_hours', {
                ...form.data.opening_hours,
                [day]: { ...form.data.opening_hours[day], [edge]: value },
            });

            return;
        }

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
            contact_recipients: data.contact_recipients
                .map((item) => (item.email ?? '').trim())
                .filter((email) => email !== ''),
            contact_auto_reply: data.contact_auto_reply,
            accent: nullable(data.accent),
            typography: nullable(data.typography),
            default_theme: data.default_theme,
            favicon_media_id:
                data.favicon_media_id === ''
                    ? null
                    : Number(data.favicon_media_id),
            tax_id: nullable(data.tax_id),
            map_url: nullable(data.map_url),
            google_site_verification: nullable(data.google_site_verification),
            announcement_enabled: data.announcement_enabled,
            announcement_tone: data.announcement_tone,
            announcement_link_url: nullable(data.announcement_link_url),
            announcement_starts_on: nullable(data.announcement_starts_on),
            announcement_ends_on: nullable(data.announcement_ends_on),
            opening_hours: data.opening_hours,
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

                if (tabHasErrors(activeTab, keys)) {
                    return;
                }

                const target = tabs.find((tab) => tabHasErrors(tab, keys));

                if (target) {
                    setActiveTab(target);
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

    function sharedState(): string {
        return sharedHasErrors()
            ? t('admin.siteSettings.localeHasErrors')
            : t('admin.siteSettings.sharedTabState');
    }

    function tabName(tab: string): string {
        return tab === SHARED_TAB
            ? t('admin.siteSettings.sharedTab')
            : (locales.available.find((locale) => locale.code === tab)
                  ?.native ?? tab);
    }

    const otherTabsWithErrors = tabs
        .filter((tab) => tab !== activeTab && tabHasErrors(tab))
        .map(tabName);

    function sharedSections(): ResourceFormSection<TabValues>[] {
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
                id: 'appearance',
                title: t('admin.siteSettings.sections.appearance.title'),
                description: t(
                    'admin.siteSettings.sections.appearance.description',
                ),
                fields: [
                    {
                        type: 'select',
                        name: 'accent',
                        label: t('admin.siteSettings.fields.accent'),
                        hint: t('admin.siteSettings.fields.accentHelp'),
                        placeholder: t(
                            'admin.siteSettings.fields.accentFromConfig',
                        ),
                        options: ACCENTS.map((accent) => ({
                            value: accent,
                            label: t(`admin.siteSettings.accents.${accent}`),
                        })),
                    },
                    {
                        type: 'select',
                        name: 'typography',
                        label: t('admin.siteSettings.fields.typography'),
                        hint: t('admin.siteSettings.fields.typographyHelp'),
                        placeholder: t(
                            'admin.siteSettings.typographyPresets.default',
                        ),
                        options: TYPOGRAPHY_PRESETS.map((preset) => ({
                            value: preset,
                            label: t(
                                `admin.siteSettings.typographyPresets.${preset}`,
                            ),
                        })),
                    },
                    {
                        type: 'select',
                        name: 'default_theme',
                        label: t('admin.siteSettings.fields.defaultTheme'),
                        hint: t('admin.siteSettings.fields.defaultThemeHelp'),
                        required: true,
                        options: THEMES.map((theme) => ({
                            value: theme,
                            label: t(`admin.siteSettings.themes.${theme}`),
                        })),
                    },
                    {
                        type: 'image',
                        name: 'favicon_media_id',
                        label: t('admin.siteSettings.fields.favicon'),
                        hint: t('admin.siteSettings.fields.faviconHelp'),
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
                    {
                        type: 'text',
                        name: 'tax_id',
                        label: t('admin.siteSettings.fields.taxId'),
                        hint: t('admin.siteSettings.fields.taxIdHelp'),
                    },
                    {
                        type: 'text',
                        name: 'map_url',
                        inputType: 'url',
                        label: t('admin.siteSettings.fields.mapUrl'),
                        hint: t('admin.siteSettings.fields.mapUrlHelp'),
                        placeholder: 'https://',
                    },
                ],
            },
            {
                id: 'hours',
                title: t('admin.siteSettings.sections.hours.title'),
                description: t('admin.siteSettings.sections.hours.description'),
                fields: WEEKDAYS.flatMap((day) => [
                    {
                        type: 'text' as const,
                        name: hoursField(day, 'opens'),
                        label: t('admin.siteSettings.fields.opens', {
                            day: t(`weekdays.${day}`),
                        }),
                        placeholder: '09:00',
                    },
                    {
                        type: 'text' as const,
                        name: hoursField(day, 'closes'),
                        label: t('admin.siteSettings.fields.closes', {
                            day: t(`weekdays.${day}`),
                        }),
                        placeholder: '17:00',
                    },
                ]),
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
                    {
                        type: 'text',
                        name: 'google_site_verification',
                        label: t(
                            'admin.siteSettings.fields.googleVerification',
                        ),
                        hint: t(
                            'admin.siteSettings.fields.googleVerificationHelp',
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
                        type: 'repeater',
                        name: 'contact_recipients',
                        label: t('admin.siteSettings.fields.recipients'),
                        hint: t('admin.siteSettings.fields.recipientsHelp'),
                        maxItems: MAX_RECIPIENTS,
                        newItem: () => ({ email: '' }),
                        labels: {
                            add: t('admin.siteSettings.fields.recipientAdd'),
                            item: (position) =>
                                t('admin.siteSettings.fields.recipientItem', {
                                    position,
                                }),
                        },
                        itemFields: [
                            {
                                type: 'text',
                                name: 'email',
                                label: t('admin.siteSettings.fields.recipient'),
                                required: true,
                            },
                        ],
                    },
                    {
                        type: 'switch',
                        name: 'contact_auto_reply',
                        label: t('admin.siteSettings.fields.autoReply'),
                        hint: t('admin.siteSettings.fields.autoReplyHelp'),
                    },
                ],
            },
            {
                id: 'announcement',
                title: t('admin.siteSettings.sections.announcement.title'),
                description: t(
                    'admin.siteSettings.sections.announcement.description',
                ),
                fields: [
                    {
                        type: 'switch',
                        name: 'announcement_enabled',
                        label: t(
                            'admin.siteSettings.fields.announcementEnabled',
                        ),
                        hint: t(
                            'admin.siteSettings.fields.announcementEnabledHelp',
                        ),
                    },
                    {
                        type: 'select',
                        name: 'announcement_tone',
                        label: t('admin.siteSettings.fields.announcementTone'),
                        hint: t(
                            'admin.siteSettings.fields.announcementToneHelp',
                        ),
                        required: true,
                        options: ANNOUNCEMENT_TONES.map((tone) => ({
                            value: tone,
                            label: t(
                                `admin.siteSettings.announcementTones.${tone}`,
                            ),
                        })),
                    },
                    {
                        type: 'text',
                        name: 'announcement_link_url',
                        label: t(
                            'admin.siteSettings.fields.announcementLinkUrl',
                        ),
                        hint: t(
                            'admin.siteSettings.fields.announcementLinkUrlHelp',
                        ),
                    },
                    {
                        type: 'date',
                        name: 'announcement_starts_on',
                        label: t(
                            'admin.siteSettings.fields.announcementStartsOn',
                        ),
                        hint: t(
                            'admin.siteSettings.fields.announcementStartsOnHelp',
                        ),
                    },
                    {
                        type: 'date',
                        name: 'announcement_ends_on',
                        label: t(
                            'admin.siteSettings.fields.announcementEndsOn',
                        ),
                        hint: t(
                            'admin.siteSettings.fields.announcementEndsOnHelp',
                        ),
                    },
                ],
            },
        ];
    }

    function textSections(
        locale: App.Data.Content.ContentLocaleData,
        values: TabValues,
    ): ResourceFormSection<TabValues>[] {
        return [
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
                            length: String(values.seo_title).length,
                        }),
                    },
                    {
                        type: 'textarea',
                        name: 'seo_description',
                        rows: 3,
                        label: t('admin.siteSettings.fields.seoDescription'),
                        hint: t(
                            'admin.siteSettings.fields.seoDescriptionCounter',
                            { length: String(values.seo_description).length },
                        ),
                    },
                    {
                        type: 'textarea',
                        name: 'auto_reply_text',
                        rows: 4,
                        label: t('admin.siteSettings.fields.autoReplyText'),
                        hint: t('admin.siteSettings.fields.autoReplyTextHelp'),
                    },
                    {
                        type: 'text',
                        name: 'announcement_text',
                        label: t('admin.siteSettings.fields.announcementText'),
                        hint: t(
                            'admin.siteSettings.fields.announcementTextCounter',
                            { length: String(values.announcement_text).length },
                        ),
                    },
                    {
                        type: 'text',
                        name: 'announcement_link_label',
                        label: t(
                            'admin.siteSettings.fields.announcementLinkLabel',
                        ),
                        hint: t(
                            'admin.siteSettings.fields.announcementLinkLabelHelp',
                        ),
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

            {otherTabsWithErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.siteSettings.errorsInOtherTabs', {
                        tabs: otherTabsWithErrors.join(', '),
                    })}
                />
            )}

            <SiteSkins
                accent={form.data.accent}
                typography={form.data.typography}
                savedAccent={settings.accent ?? ''}
                savedTypography={settings.typography ?? ''}
                disabled={form.processing}
                onApply={(skin) =>
                    form.setData((data) => ({
                        ...data,
                        accent: skin.accent,
                        typography: skin.typography,
                    }))
                }
            />

            <Alert
                tone="neutral"
                title={t('admin.siteSettings.searchVisibility.title')}
                description={
                    <>
                        {t('admin.siteSettings.searchVisibility.description')}{' '}
                        <Link href={systemIndex()}>
                            {t('admin.siteSettings.searchVisibility.link')}
                        </Link>
                    </>
                }
            />

            <Tabs
                ariaLabel={t('admin.siteSettings.languagesLabel')}
                value={activeTab}
                onValueChange={setActiveTab}
                items={tabs.map((code) => {
                    const locale = locales.available.find(
                        (available) => available.code === code,
                    );
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
                        ...Object.fromEntries(
                            WEEKDAYS.flatMap((day) => [
                                [
                                    hoursField(day, 'opens'),
                                    form.data.opening_hours[day].opens,
                                ],
                                [
                                    hoursField(day, 'closes'),
                                    form.data.opening_hours[day].closes,
                                ],
                            ]),
                        ),
                        ...(locale
                            ? form.data.translations[code]
                            : toTranslationState(undefined)),
                        contact_recipients: form.data.contact_recipients,
                        contact_auto_reply: form.data.contact_auto_reply,
                        announcement_enabled: form.data.announcement_enabled,
                    } as TabValues;

                    return {
                        value: code,
                        label: t('admin.siteSettings.localeTabLabel', {
                            language: tabName(code),
                            state: locale ? localeState(code) : sharedState(),
                        }),
                        content: (
                            <ResourceForm<TabValues>
                                values={values}
                                errors={{
                                    ...errors,
                                    // The request validates plain addresses;
                                    // the repeater reads `<name>.<index>.email`.
                                    ...Object.fromEntries(
                                        form.data.contact_recipients.map(
                                            (_, position) => [
                                                `contact_recipients.${position}.email`,
                                                errors[
                                                    `contact_recipients.${position}`
                                                ],
                                            ],
                                        ),
                                    ),
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
                                sections={
                                    locale
                                        ? textSections(locale, values)
                                        : sharedSections()
                                }
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
