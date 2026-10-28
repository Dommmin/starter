import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { I18nProvider } from '@/i18n';
import AdminSiteSettingsEdit from './edit';

type Errors = Record<string, string>;

const submitMock = vi.fn();
let nextErrors: Errors | null = null;
let editorProps: App.Data.Admin.Settings.SiteSettingsEditorData;

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    function useForm<Data extends object>(initial: Data) {
        const [data, setDataState] = React.useState(initial);
        const [errors, setErrors] = React.useState<Errors>({});
        const transformRef = React.useRef<(value: Data) => unknown>(
            (value) => value,
        );

        return {
            data,
            errors,
            processing: false,
            recentlySuccessful: false,
            isDirty: false,
            setData: (
                key: keyof Data | ((current: Data) => Data),
                value?: unknown,
            ) =>
                setDataState((current) =>
                    typeof key === 'function'
                        ? key(current)
                        : { ...current, [key]: value },
                ),
            transform: (callback: (value: Data) => unknown) => {
                transformRef.current = callback;
            },
            setDefaults: () => {},
            submit: (
                route: { url: string; method: string },
                options: {
                    onError?: (errors: Errors) => void;
                    onSuccess?: () => void;
                },
            ) => {
                submitMock(route, transformRef.current(data));

                if (nextErrors) {
                    setErrors(nextErrors);
                    options.onError?.(nextErrors);
                } else {
                    options.onSuccess?.();
                }
            },
        };
    }

    return {
        useForm,
        usePage: () => ({ props: editorProps }),
        router: {
            visit: vi.fn(),
            on: () => () => {},
        },
        Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
        Link: ({
            href,
            children,
            prefetch: _prefetch,
            ...props
        }: {
            href: string | { url: string };
            children: ReactNode;
            prefetch?: boolean;
            [key: string]: unknown;
        }) => (
            <a href={typeof href === 'string' ? href : href.url} {...props}>
                {children}
            </a>
        ),
    };
});

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

function blankTranslation(): App.Data.Admin.Settings.SiteSettingTranslationFormData {
    return {
        tagline: null,
        footerText: null,
        seoTitle: null,
        seoDescription: null,
        autoReplyText: null,
    };
}

function makeEditor(
    settings: Partial<App.Data.Admin.Settings.SiteSettingsFormData> = {},
): App.Data.Admin.Settings.SiteSettingsEditorData {
    return {
        settings: {
            updatedAt: null,
            siteName: 'Starter',
            logoMediaId: null,
            ogImageMediaId: null,
            contactEmail: null,
            contactPhone: null,
            addressLine: null,
            postalCode: null,
            city: null,
            countryCode: null,
            contactRecipientEmail: null,
            socialLinks: {
                facebook: '',
                instagram: '',
                linkedin: '',
                x: '',
                youtube: '',
                tiktok: '',
                github: '',
            },
            translations: { en: blankTranslation(), pl: blankTranslation() },
            accent: null,
            typography: null,
            defaultTheme: 'system',
            faviconMediaId: null,
            taxId: null,
            mapUrl: null,
            openingHours: {
                monday: { opens: '', closes: '' },
                tuesday: { opens: '', closes: '' },
                wednesday: { opens: '', closes: '' },
                thursday: { opens: '', closes: '' },
                friday: { opens: '', closes: '' },
                saturday: { opens: '', closes: '' },
                sunday: { opens: '', closes: '' },
            },
            googleSiteVerification: null,
            plausibleDomain: null,
            contactRecipients: [],
            contactAutoReply: false,
            ...settings,
        },
        locales: {
            available: [
                { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
                { code: 'pl', name: 'Polish', native: 'Polski', dir: 'ltr' },
            ],
            default: 'en',
        },
        socialNetworks: [
            { network: 'facebook', label: 'Facebook' },
            { network: 'linkedin', label: 'LinkedIn' },
        ],
    };
}

const initialPage = {
    props: {
        i18n: {
            area: 'admin',
            locale: 'en',
            defaultLocale: 'en',
            messages: {},
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const mountedRoots: Root[] = [];

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
        );
    });

    return container;
}

function field(container: HTMLElement, name: string): HTMLInputElement {
    return container.querySelector<HTMLInputElement>(
        `[role="tabpanel"] [name="${name}"]`,
    )!;
}

async function type(element: HTMLInputElement, value: string) {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype,
            'value',
        )!.set!.call(element, value);
        element.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

/** Selects a tab by value (Radix trigger ids end with `-trigger-{value}`). */
async function selectTab(container: HTMLElement, value: string) {
    await act(async () => {
        container
            .querySelector<HTMLButtonElement>(
                `[role="tab"][id$="-trigger-${value}"]`,
            )
            ?.focus();
    });
}

async function submit(container: HTMLElement) {
    const button = container.querySelector<HTMLButtonElement>(
        '[role="tabpanel"] button[type="submit"]',
    )!;

    await act(async () => {
        button.click();
    });
}

/** Value of the selected tab (Radix trigger ids end with `-trigger-{value}`). */
function activeTab(container: HTMLElement): string | undefined {
    return container
        .querySelector('[role="tab"][aria-selected="true"]')
        ?.id.split('-trigger-')[1];
}

function describedByTexts(element: HTMLElement): (string | null)[] {
    return (element.getAttribute('aria-describedby') ?? '')
        .split(' ')
        .map((id) => document.getElementById(id)?.textContent ?? null);
}

beforeEach(() => {
    nextErrors = null;
});

afterEach(async () => {
    submitMock.mockClear();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('SiteSettingsForm', () => {
    it('renders the shared settings in their own tab and only the texts in each language tab', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminSiteSettingsEdit />);

        expect(container.querySelectorAll('[role="tab"]')).toHaveLength(3);
        expect(field(container, 'site_name').value).toBe('Starter');
        expect(field(container, 'social_links.facebook')).not.toBeNull();
        expect(field(container, 'social_links.linkedin')).not.toBeNull();
        expect(
            [...container.querySelectorAll('button')].some(
                (button) =>
                    button.textContent ===
                    'admin.siteSettings.fields.recipientAdd',
            ),
        ).toBe(true);
        expect(
            container.querySelector(
                '[role="switch"][id$="contact_auto_reply"]',
            ),
        ).not.toBeNull();
        expect(field(container, 'seo_title')).toBeNull();

        await selectTab(container, 'en');

        expect(field(container, 'seo_title')).not.toBeNull();
        expect(field(container, 'auto_reply_text')).not.toBeNull();
        expect(field(container, 'site_name')).toBeNull();
        expect(field(container, 'contact_email')).toBeNull();
        expect(field(container, 'social_links.facebook')).toBeNull();
    });

    it('submits empty optional values as null with the version, social links and translations', async () => {
        editorProps = makeEditor({ updatedAt: '2026-09-01T10:00:00+00:00' });
        const container = await render(<AdminSiteSettingsEdit />);

        await type(field(container, 'site_name'), 'Acme');
        await type(
            field(container, 'social_links.linkedin'),
            'https://www.linkedin.com/company/acme',
        );
        await selectTab(container, 'en');
        await type(field(container, 'tagline'), 'We build things');
        await submit(container);

        const [route, payload] = submitMock.mock.lastCall!;
        expect(route.url).toBe('/admin/site-settings');
        expect(route.method).toBe('put');
        expect(payload).toMatchObject({
            updated_at: '2026-09-01T10:00:00+00:00',
            site_name: 'Acme',
            logo_media_id: null,
            og_image_media_id: null,
            contact_email: null,
            contact_recipient_email: null,
            social_links: {
                linkedin: 'https://www.linkedin.com/company/acme',
                facebook: '',
            },
            translations: {
                en: { tagline: 'We build things', seo_title: '' },
                pl: { tagline: '' },
            },
        });
    });

    it('shows server errors next to the fields they belong to', async () => {
        editorProps = makeEditor();
        nextErrors = {
            contact_email: 'The contact email must be a valid email address.',
            'social_links.facebook': 'The link must start with https://.',
            'translations.en.seo_title':
                'The title may not exceed 70 characters.',
        };
        const container = await render(<AdminSiteSettingsEdit />);

        await submit(container);

        expect(describedByTexts(field(container, 'contact_email'))).toContain(
            'The contact email must be a valid email address.',
        );
        expect(
            describedByTexts(field(container, 'social_links.facebook')),
        ).toContain('The link must start with https://.');
        expect(
            field(container, 'contact_email').getAttribute('aria-invalid'),
        ).toBe('true');
        expect(container.textContent).toContain(
            'admin.siteSettings.errorsInOtherTabs',
        );

        await selectTab(container, 'en');

        expect(describedByTexts(field(container, 'seo_title'))).toContain(
            'The title may not exceed 70 characters.',
        );
    });

    it('switches to the shared tab when only shared settings have errors', async () => {
        editorProps = makeEditor();
        nextErrors = { site_name: 'The site name is required.' };
        const container = await render(<AdminSiteSettingsEdit />);

        await selectTab(container, 'pl');
        await submit(container);

        expect(activeTab(container)).toBe('shared');
        expect(describedByTexts(field(container, 'site_name'))).toContain(
            'The site name is required.',
        );
    });

    it('switches to the language whose texts have errors', async () => {
        editorProps = makeEditor();
        nextErrors = {
            'translations.pl.seo_description': 'Too long.',
        };
        const container = await render(<AdminSiteSettingsEdit />);

        await submit(container);

        expect(activeTab(container)).toBe('pl');
        const description = container.querySelector<HTMLTextAreaElement>(
            '[role="tabpanel"] textarea[name="seo_description"]',
        )!;
        expect(describedByTexts(description)).toContain('Too long.');
    });

    it('links to the System page for the site-wide noindex switch', async () => {
        editorProps = makeEditor({});
        const container = await render(<AdminSiteSettingsEdit />);

        const link = Array.from(container.querySelectorAll('a')).find(
            (anchor) =>
                anchor.textContent ===
                'admin.siteSettings.searchVisibility.link',
        );
        expect(link?.getAttribute('href')).toBe('/admin/system');
    });

    it('applies a skin to the accent and typography fields and saves it with the form', async () => {
        editorProps = makeEditor({ accent: 'default', typography: null });
        const container = await render(<AdminSiteSettingsEdit />);

        expect(
            container.querySelectorAll('[data-appearance-preview][inert]'),
        ).toHaveLength(3);
        expect(
            container.querySelectorAll(
                'button[aria-label="admin.siteSettings.skins.applyNamed"]',
            ),
        ).toHaveLength(2);

        expect(container.textContent).toContain(
            'admin.siteSettings.skins.current',
        );
        expect(container.textContent).not.toContain(
            'admin.siteSettings.skins.unsaved',
        );

        await act(async () => {
            container
                .querySelectorAll<HTMLButtonElement>(
                    'button[aria-label="admin.siteSettings.skins.applyNamed"]',
                )[0]
                .click();
        });

        // Applied but not saved: no skin claims to be the selected one.
        expect(container.textContent).toContain(
            'admin.siteSettings.skins.unsaved',
        );
        expect(container.textContent).not.toContain(
            'admin.siteSettings.skins.current',
        );

        await submit(container);

        expect(submitMock.mock.lastCall![1]).toMatchObject({
            accent: 'blue',
            typography: 'classic',
        });
        expect(
            container.querySelectorAll(
                'button[aria-label="admin.siteSettings.skins.applyNamed"]',
            ),
        ).toHaveLength(2);
    });
});
