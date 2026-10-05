import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { BrandLogo } from './brand-logo';
import { Button, type ButtonProps } from './button';
import { PublicChrome } from './public-chrome';

const sharedProps = vi.hoisted(() => ({
    current: {} as Record<string, unknown>,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: null },
            i18n: { alternateUrls: {} },
            ...sharedProps.current,
        },
    }),
    router: {
        on: () => () => {},
    },
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
    }) => {
        const targetHref = typeof href === 'string' ? href : href.url;
        return (
            <a href={targetHref} data-inertia-link="" {...props}>
                {children}
            </a>
        );
    },
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

const initialPage = {
    props: {
        i18n: {
            area: 'public',
            locale: 'en',
            defaultLocale: 'en',
            messages: {
                brand: {
                    name: 'Acme',
                    firstPart: 'Punkt',
                    secondPart: 'Startowy',
                },
            },
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

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

function makeSite(
    overrides: Partial<App.Data.Settings.SiteSettingsData> = {},
): App.Data.Settings.SiteSettingsData {
    return {
        name: 'Panel Name',
        isCustomized: true,
        logo: null,
        tagline: null,
        footerText: 'Panel footer line',
        contact: {
            email: 'office@panel.test',
            phone: '+48 123 456 789',
            address: null,
        },
        social: [
            {
                network: 'github',
                label: 'GitHub',
                url: 'https://github.com/panel',
            },
        ],
        ...overrides,
    };
}

afterEach(async () => {
    sharedProps.current = {};
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('PublicChrome', () => {
    it('frames the page with the header, the skip-link target and the footer', async () => {
        const container = await render(
            <PublicChrome
                navItems={[
                    {
                        id: 'features',
                        kind: 'anchor',
                        label: 'Features',
                        href: '#features',
                    },
                ]}
                footer={{
                    contact: { email: 'hello@example.test' },
                }}
            >
                <p>Page body</p>
            </PublicChrome>,
        );

        const header = container.querySelector('header');
        const main = container.querySelector('main#main-content');
        const footer = container.querySelector('footer');

        expect(header?.querySelector('a[href="#main-content"]')).not.toBeNull();
        expect(header?.querySelector('a[href="#features"]')).not.toBeNull();
        expect(main?.textContent).toBe('Page body');
        expect(
            footer?.querySelector('a[href="mailto:hello@example.test"]'),
        ).not.toBeNull();
        expect(footer?.textContent).toContain(
            `© ${new Date().getFullYear()} Acme`,
        );
        // Header, main and footer are siblings, in document order.
        expect(header?.nextElementSibling).toBe(main);
        expect(main?.nextElementSibling).toBe(footer);
    });

    it('renders the shared navigation menus unless the page passes its own', async () => {
        sharedProps.current = {
            navigation: {
                header: [
                    {
                        id: 1,
                        label: 'Shared blog',
                        href: '/articles',
                        kind: 'internal',
                        newTab: false,
                        children: [],
                    },
                ],
                footer: [
                    {
                        id: 2,
                        label: 'Legal',
                        kind: 'group',
                        newTab: false,
                        children: [
                            {
                                id: 3,
                                label: 'Privacy',
                                href: '/privacy-policy',
                                kind: 'internal',
                                newTab: false,
                                children: [],
                            },
                        ],
                    },
                ],
            },
        };

        const shared = await render(
            <PublicChrome>
                <p>Body</p>
            </PublicChrome>,
        );

        expect(
            shared.querySelector('header a[href="/articles"]')?.textContent,
        ).toContain('Shared blog');
        expect(shared.querySelector('footer')?.textContent).toContain('Legal');
        expect(
            shared.querySelector('footer a[href="/privacy-policy"]'),
        ).not.toBeNull();

        const own = await render(
            <PublicChrome
                navItems={[
                    { id: 'own', kind: 'anchor', label: 'Own', href: '#own' },
                ]}
                footer={{ groups: [] }}
            >
                <p>Body</p>
            </PublicChrome>,
        );

        expect(own.querySelector('header a[href="#own"]')).not.toBeNull();
        expect(own.querySelector('header a[href="/articles"]')).toBeNull();
        expect(
            own.querySelector('footer a[href="/privacy-policy"]'),
        ).toBeNull();
    });

    it('offers the theme switcher in the footer to guests only', async () => {
        const themeButton = (root: Element | null | undefined) =>
            root?.querySelector('button[aria-label^="a11y.themeSwitcher"]');

        const guest = await render(
            <PublicChrome>
                <p>Page body</p>
            </PublicChrome>,
        );

        expect(themeButton(guest.querySelector('footer'))).not.toBeNull();
        expect(themeButton(guest.querySelector('header'))).toBeNull();

        sharedProps.current = {
            auth: {
                user: { id: 1, name: 'Ada Admin', email: 'ada@example.test' },
            },
        };
        const signedIn = await render(
            <PublicChrome>
                <p>Page body</p>
            </PublicChrome>,
        );

        expect(themeButton(signedIn.querySelector('footer'))).toBeNull();
        expect(themeButton(signedIn.querySelector('header'))).toBeNull();
    });

    it('uses the copyright line the page passes', async () => {
        const container = await render(
            <PublicChrome footer={{ copyright: '© 2026 Custom' }}>
                <p>Body</p>
            </PublicChrome>,
        );

        expect(container.querySelector('footer')?.textContent).toContain(
            '© 2026 Custom',
        );
    });
});

describe('PublicChrome site settings defaults', () => {
    it('fills the brand, copyright, contact and social links from the shared site prop', async () => {
        sharedProps.current.site = makeSite();
        const container = await render(
            <PublicChrome>
                <p>Body</p>
            </PublicChrome>,
        );

        const header = container.querySelector('header');
        const footer = container.querySelector('footer');

        expect(header?.textContent).toContain('Panel Name');
        expect(footer?.textContent).toContain(
            `© ${new Date().getFullYear()} Panel footer line`,
        );
        expect(
            footer?.querySelector('a[href="mailto:office@panel.test"]'),
        ).not.toBeNull();
        expect(
            footer?.querySelector('a[href="https://github.com/panel"]'),
        ).not.toBeNull();
    });

    it('lets the page override the defaults and keeps the catalog brand before the first save', async () => {
        sharedProps.current.site = makeSite({
            isCustomized: false,
            footerText: null,
        });
        const container = await render(
            <PublicChrome
                footer={{
                    copyright: '© 2026 Page line',
                    contact: { email: 'page@example.test' },
                    social: [],
                }}
            >
                <p>Body</p>
            </PublicChrome>,
        );

        const header = container.querySelector('header');
        const footer = container.querySelector('footer');

        expect(header?.textContent).not.toContain('Panel Name');
        expect(footer?.textContent).toContain('© 2026 Page line');
        expect(
            footer?.querySelector('a[href="mailto:page@example.test"]'),
        ).not.toBeNull();
        expect(
            footer?.querySelector('a[href="mailto:office@panel.test"]'),
        ).toBeNull();
        expect(
            footer?.querySelector('a[href="https://github.com/panel"]'),
        ).toBeNull();
    });

    it('uses the site logo with the site name as its accessible name', async () => {
        sharedProps.current.site = makeSite({
            logo: {
                sources: [],
                src: '/storage/logo-640.png',
                srcset: '/storage/logo-640.png 640w',
                width: 640,
                height: 360,
            },
        });
        const container = await render(
            <PublicChrome>
                <p>Body</p>
            </PublicChrome>,
        );

        const logo = container.querySelector<HTMLImageElement>(
            'header img[src="/storage/logo-640.png"]',
        );
        expect(logo?.alt).toBe('Panel Name');
    });
});

describe('BrandLogo', () => {
    it('renders the saved name in one tone', async () => {
        const container = await render(<BrandLogo name="Panel Name" />);

        const link = container.querySelector('a');
        expect(link?.getAttribute('aria-label')).toBe('Panel Name');
        expect(link?.textContent).toBe('Panel Name');
        expect(link?.querySelectorAll('span span')).toHaveLength(0);
    });

    it('renders the two-tone catalog brand without a name', async () => {
        const container = await render(<BrandLogo />);

        const link = container.querySelector('a');
        expect(link?.getAttribute('aria-label')).toBe('Acme');
        expect(link?.textContent).toBe('Punkt Startowy');
        expect(link?.querySelectorAll('span span')).toHaveLength(1);
    });
});

describe('Button download', () => {
    it('renders a plain download anchor instead of an Inertia link', async () => {
        const container = await render(
            <>
                <Button href="/exports/users.csv" download="users.csv">
                    Export
                </Button>
                <Button href="/media/1/original" download variant="outline">
                    Original
                </Button>
            </>,
        );

        const named = container.querySelector<HTMLAnchorElement>(
            'a[href="/exports/users.csv"]',
        );
        const plain = container.querySelector<HTMLAnchorElement>(
            'a[href="/media/1/original"]',
        );

        expect(named?.getAttribute('download')).toBe('users.csv');
        expect(named?.hasAttribute('data-inertia-link')).toBe(false);
        expect(plain?.getAttribute('download')).toBe('');
        expect(plain?.hasAttribute('data-inertia-link')).toBe(false);
    });

    it('keeps using an Inertia link without download', async () => {
        const container = await render(<Button href="/users">Users</Button>);

        const link = container.querySelector('a[href="/users"]');
        expect(link?.hasAttribute('data-inertia-link')).toBe(true);
        expect(link?.hasAttribute('download')).toBe(false);
    });

    it('keeps a closed styling API', () => {
        const props: ButtonProps = {
            children: 'Export',
            download: true,
            // @ts-expect-error ADR-019: screens cannot restyle primitives.
            className: 'mt-4',
        };

        expect(props.download).toBe(true);
    });
});
