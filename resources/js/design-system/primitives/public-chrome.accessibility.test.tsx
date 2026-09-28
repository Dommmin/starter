import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
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
                brand: { name: 'Acme' },
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
