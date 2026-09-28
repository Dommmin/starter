import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
            setData: (key: keyof Data, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
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

async function submit(container: HTMLElement) {
    const button = container.querySelector<HTMLButtonElement>(
        '[role="tabpanel"] button[type="submit"]',
    )!;

    await act(async () => {
        button.click();
    });
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
    it('renders the shared settings, one field per social network and the texts of the active language', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminSiteSettingsEdit />);

        expect(field(container, 'site_name').value).toBe('Starter');
        expect(field(container, 'social_links.facebook')).not.toBeNull();
        expect(field(container, 'social_links.linkedin')).not.toBeNull();
        expect(field(container, 'contact_recipient_email').type).toBe('email');
        expect(field(container, 'seo_title')).not.toBeNull();
        expect(container.querySelectorAll('[role="tab"]')).toHaveLength(2);
    });

    it('submits empty optional values as null with the version, social links and translations', async () => {
        editorProps = makeEditor({ updatedAt: '2026-09-01T10:00:00+00:00' });
        const container = await render(<AdminSiteSettingsEdit />);

        await type(field(container, 'site_name'), 'Acme');
        await type(
            field(container, 'social_links.linkedin'),
            'https://www.linkedin.com/company/acme',
        );
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
        expect(describedByTexts(field(container, 'seo_title'))).toContain(
            'The title may not exceed 70 characters.',
        );
        expect(
            field(container, 'contact_email').getAttribute('aria-invalid'),
        ).toBe('true');
    });

    it('switches to the language whose texts have errors', async () => {
        editorProps = makeEditor();
        nextErrors = {
            'translations.pl.seo_description': 'Too long.',
        };
        const container = await render(<AdminSiteSettingsEdit />);

        await submit(container);

        const tabs = container.querySelectorAll<HTMLElement>('[role="tab"]');
        expect(tabs[0].getAttribute('aria-selected')).toBe('false');
        expect(tabs[1].getAttribute('aria-selected')).toBe('true');
        const description = container.querySelector<HTMLTextAreaElement>(
            '[role="tabpanel"] textarea[name="seo_description"]',
        )!;
        expect(describedByTexts(description)).toContain('Too long.');
    });
});
