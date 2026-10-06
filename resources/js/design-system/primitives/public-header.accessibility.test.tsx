import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { PublicHeader } from './public-header';

const currentPage = vi.hoisted(() => ({
    url: '/',
    user: null as Record<string, unknown> | null,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        url: currentPage.url,
        props: {
            auth: { user: currentPage.user },
            i18n: { alternateUrls: {} },
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
            messages: {},
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
    currentPage.url = '/';
    currentPage.user = null;
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('PublicHeader', () => {
    it('renders no mobile menu trigger when no nav items are supplied', async () => {
        const container = await render(<PublicHeader />);

        expect(
            container.querySelector('button[aria-label="a11y.openMenu"]'),
        ).toBeNull();
    });

    it('renders the nav items inline and opens the mobile nav with the same items', async () => {
        const container = await render(
            <PublicHeader
                navItems={[
                    {
                        id: 'features',
                        kind: 'anchor',
                        label: 'Features',
                        href: '#features',
                    },
                ]}
            />,
        );

        expect(container.querySelector('a[href="#features"]')).not.toBeNull();

        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="a11y.openMenu"]',
        );
        expect(trigger).not.toBeNull();

        await act(async () => {
            trigger?.click();
        });

        const dialogNav = document.querySelector(
            'nav[aria-label="nav.menuTitle"]',
        );
        expect(dialogNav?.textContent).toContain('Features');

        const closeButton = document.querySelector<HTMLButtonElement>(
            'button[aria-label="a11y.closeMenu"]',
        );
        expect(closeButton).not.toBeNull();

        await act(async () => {
            closeButton?.click();
        });

        expect(
            document.querySelector('nav[aria-label="nav.menuTitle"]'),
        ).toBeNull();
    });

    it('renders each link kind with the element and attributes it requires', async () => {
        const container = await render(
            <PublicHeader
                navItems={[
                    {
                        id: 'articles',
                        kind: 'internal',
                        label: 'Articles',
                        href: '/articles',
                    },
                    {
                        id: 'features',
                        kind: 'anchor',
                        label: 'Features',
                        href: '#features',
                    },
                    {
                        id: 'docs',
                        kind: 'external',
                        label: 'Docs',
                        href: 'https://docs.example.test',
                        newTab: true,
                    },
                ]}
            />,
        );

        const desktop = container;
        const internal = desktop?.querySelector('a[href="/articles"]');
        const anchor = desktop?.querySelector('a[href="#features"]');
        const external = desktop?.querySelector<HTMLAnchorElement>(
            'a[href="https://docs.example.test"]',
        );

        expect(internal?.hasAttribute('data-inertia-link')).toBe(true);
        expect(anchor?.hasAttribute('data-inertia-link')).toBe(false);
        expect(external?.hasAttribute('data-inertia-link')).toBe(false);
        expect(external?.target).toBe('_blank');
        expect(external?.rel).toBe('noopener noreferrer');
        expect(external?.textContent).toContain('a11y.opensInNewTab');
    });

    it('opens a disclosure submenu, closes it with Escape and returns focus', async () => {
        const container = await render(
            <PublicHeader
                navItems={[
                    {
                        id: 'company',
                        kind: 'group',
                        label: 'Company',
                        children: [
                            {
                                id: 'about',
                                kind: 'internal',
                                label: 'About',
                                href: '/about',
                            },
                            {
                                id: 'blog',
                                kind: 'external',
                                label: 'Blog',
                                href: 'https://blog.example.test',
                            },
                        ],
                    },
                ]}
            />,
        );

        const trigger = Array.from(
            container.querySelectorAll<HTMLButtonElement>('button'),
        ).find((button) => button.textContent === 'Company');

        expect(trigger).toBeDefined();
        expect(trigger?.getAttribute('aria-expanded')).toBe('false');
        expect(container.querySelector('[role="menu"]')).toBeNull();
        expect(container.querySelector('a[href="/about"]')).toBeNull();

        await act(async () => {
            trigger?.click();
        });

        expect(trigger?.getAttribute('aria-expanded')).toBe('true');
        const submenu = container.querySelector('ul[aria-label="nav.submenu"]');
        const about =
            submenu?.querySelector<HTMLAnchorElement>('a[href="/about"]');
        expect(about).not.toBeNull();
        expect(
            submenu
                ?.querySelector('a[href="https://blog.example.test"]')
                ?.getAttribute('rel'),
        ).toBe('noopener noreferrer');

        about?.focus();
        await act(async () => {
            about?.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
            );
        });

        expect(trigger?.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger);
        expect(container.querySelector('a[href="/about"]')).toBeNull();
    });

    it('closes the submenu when focus leaves it', async () => {
        const container = await render(
            <PublicHeader
                navItems={[
                    {
                        id: 'services',
                        kind: 'internal',
                        label: 'Services',
                        href: '/services',
                        children: [
                            {
                                id: 'design',
                                kind: 'internal',
                                label: 'Design',
                                href: '/services/design',
                            },
                        ],
                    },
                ]}
            />,
        );

        // A parent with its own href is a link next to the toggle.
        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="nav.submenu"]',
        );

        await act(async () => {
            trigger?.click();
        });

        const links = container.querySelectorAll(
            'ul[aria-label="nav.submenu"] a',
        );
        expect(
            Array.from(links).map((link) => link.getAttribute('href')),
        ).toEqual(['/services/design']);

        await act(async () => {
            trigger?.dispatchEvent(
                new FocusEvent('focusout', {
                    bubbles: true,
                    relatedTarget: document.body,
                }),
            );
        });

        expect(trigger?.getAttribute('aria-expanded')).toBe('false');
    });

    it('marks only the link of the current section as the current page', async () => {
        currentPage.url = '/articles/launch?page=2';
        const container = await render(
            <PublicHeader
                navItems={[
                    {
                        id: 'features',
                        kind: 'anchor',
                        label: 'Features',
                        href: '/#features',
                    },
                    {
                        id: 'articles',
                        kind: 'internal',
                        label: 'Articles',
                        href: 'http://localhost/articles',
                    },
                    {
                        id: 'home',
                        kind: 'internal',
                        label: 'Home',
                        href: '/',
                    },
                ]}
            />,
        );

        const current = container.querySelectorAll('[aria-current="page"]');

        expect(current).toHaveLength(1);
        expect(current[0].textContent).toBe('Articles');
    });

    it('keeps only the main menu inside the navigation landmark', async () => {
        const container = await render(
            <PublicHeader
                navItems={[
                    {
                        id: 'articles',
                        kind: 'internal',
                        label: 'Articles',
                        href: '/articles',
                    },
                ]}
            />,
        );

        const landmarks = container.querySelectorAll('nav');
        const main = container.querySelector(
            'nav[aria-label="a11y.mainNavigation"]',
        );

        expect(landmarks).toHaveLength(1);
        expect(main?.querySelector('a[href="/articles"]')).not.toBeNull();
        expect(main?.querySelectorAll('button')).toHaveLength(0);
        expect(main?.textContent).not.toContain('nav.login');
    });

    it('offers the theme choice in the account menu of a signed-in user', async () => {
        currentPage.user = {
            id: 1,
            name: 'Ada Admin',
            email: 'ada@example.test',
        };
        const container = await render(<PublicHeader />);

        expect(
            container.querySelector('button[aria-label^="a11y.themeSwitcher"]'),
        ).toBeNull();

        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="a11y.userMenu"]',
        );
        await act(async () => {
            trigger?.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
            );
        });

        const items = Array.from(
            document.querySelectorAll('[role="menuitemradio"]'),
        ).map((item) => item.textContent);

        expect(items).toEqual(['theme.light', 'theme.dark', 'theme.system']);
    });

    it('keeps a linked parent out of its own submenu', async () => {
        const container = await render(
            <PublicHeader
                navItems={[
                    {
                        id: 'articles',
                        kind: 'internal',
                        label: 'Articles',
                        href: '/articles',
                        children: [
                            {
                                id: 'docs',
                                kind: 'external',
                                label: 'Docs',
                                href: 'https://docs.example.test',
                            },
                        ],
                    },
                ]}
            />,
        );

        const nav = container.querySelector(
            'nav[aria-label="a11y.mainNavigation"]',
        );
        const toggle = nav?.querySelector<HTMLButtonElement>(
            'button[aria-label="nav.submenu"]',
        );

        expect(nav?.querySelector('a[href="/articles"]')?.textContent).toBe(
            'Articles',
        );
        expect(toggle).not.toBeNull();

        await act(async () => {
            toggle?.click();
        });

        const submenu = nav?.querySelector('ul[aria-label="nav.submenu"]');
        expect(
            Array.from(submenu?.querySelectorAll('a') ?? []).map((link) =>
                link.getAttribute('href'),
            ),
        ).toEqual(['https://docs.example.test']);
    });
});
