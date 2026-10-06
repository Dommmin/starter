import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { BrandLogo } from './brand-logo';
import { Footer } from './footer';
import { MobileNav } from './mobile-nav';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: '/', props: {} }),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

/**
 * Lets a popup module loaded on first opening (see `lazy-popup.ts`) arrive
 * and mount.
 */
async function settleLazyPopup(load: () => Promise<unknown>): Promise<void> {
    await act(async () => {
        await load();
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

const initialPage = {
    props: {
        i18n: {
            locale: 'en',
            messages: {},
            fallback: 'en',
            dir: 'ltr',
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
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('MobileNav', () => {
    it('opens the panel and exposes its navigation items', async () => {
        const container = await render(
            <MobileNav
                title="Menu"
                openLabel="Open menu"
                closeLabel="Close menu"
                items={[
                    {
                        id: 'pricing',
                        kind: 'internal',
                        label: 'Pricing',
                        href: '/pricing',
                    },
                    {
                        id: 'about',
                        kind: 'internal',
                        label: 'About',
                        href: '/about',
                    },
                ]}
            />,
        );

        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Open menu"]',
        );

        await act(async () => {
            trigger?.click();
        });
        await settleLazyPopup(() => import('./mobile-nav-panel'));

        const nav = document.querySelector('nav[aria-label="Menu"]');
        expect(nav?.textContent).toContain('Pricing');
        expect(nav?.textContent).toContain('About');
        expect(
            document.querySelector('button[aria-label="Close menu"]'),
        ).not.toBeNull();
    });
});

describe('MobileNav nesting', () => {
    it('renders groups as headings with nested links and external links as plain anchors', async () => {
        const container = await render(
            <MobileNav
                title="Menu"
                openLabel="Open menu"
                closeLabel="Close menu"
                items={[
                    {
                        id: 'company',
                        kind: 'group',
                        label: 'Company',
                        children: [
                            {
                                id: 'team',
                                kind: 'anchor',
                                label: 'Team',
                                href: '#team',
                            },
                            {
                                id: 'jobs',
                                kind: 'external',
                                label: 'Jobs',
                                href: 'https://jobs.example.test',
                                newTab: true,
                            },
                        ],
                    },
                ]}
            />,
        );

        await act(async () => {
            container
                .querySelector<HTMLButtonElement>(
                    'button[aria-label="Open menu"]',
                )
                ?.click();
        });
        await settleLazyPopup(() => import('./mobile-nav-panel'));

        const nav = document.querySelector('nav[aria-label="Menu"]');
        const heading = nav?.querySelector('li > p');
        expect(heading?.textContent).toBe('Company');
        expect(
            nav?.querySelector('a[href="#team"]')?.closest('ul ul'),
        ).not.toBeNull();

        const external = nav?.querySelector<HTMLAnchorElement>(
            'a[href="https://jobs.example.test"]',
        );
        expect(external?.rel).toBe('noopener noreferrer');
        expect(external?.target).toBe('_blank');
        expect(external?.querySelector('.sr-only')?.textContent).toContain(
            'a11y.opensInNewTab',
        );

        // Activating an in-page anchor closes the panel (no visit replaces it).
        await act(async () => {
            nav?.querySelector<HTMLAnchorElement>('a[href="#team"]')?.click();
        });

        expect(document.querySelector('nav[aria-label="Menu"]')).toBeNull();
    });
});

describe('Footer', () => {
    it('renders nav groups as columns, contact details, social links and the copyright line', async () => {
        const container = await render(
            <Footer
                copyright="© 2026 Acme"
                groups={[
                    {
                        id: 'product',
                        kind: 'group',
                        label: 'Product',
                        children: [
                            {
                                id: 'pricing',
                                kind: 'internal',
                                label: 'Pricing',
                                href: '/pricing',
                            },
                        ],
                    },
                ]}
                contact={{
                    email: 'hello@example.test',
                    phone: '+48 600 100 200',
                    address: 'Main St 1\n00-001 City',
                }}
                social={[
                    {
                        network: 'linkedin',
                        label: 'LinkedIn',
                        url: 'https://linkedin.example.test/acme',
                    },
                ]}
            />,
        );

        expect(container.textContent).toContain('Product');
        expect(container.querySelector('a[href="/pricing"]')?.textContent).toBe(
            'Pricing',
        );
        expect(container.textContent).toContain('footer.contact');
        expect(
            container.querySelector('a[href="mailto:hello@example.test"]'),
        ).not.toBeNull();
        expect(
            container.querySelector('a[href="tel:+48600100200"]'),
        ).not.toBeNull();
        expect(container.querySelector('address')?.textContent).toBe(
            'Main St 1\n00-001 City',
        );

        expect(container.textContent).toContain('footer.social');
        const social = container.querySelector<HTMLAnchorElement>(
            'a[href="https://linkedin.example.test/acme"]',
        );
        expect(social?.rel).toBe('noopener noreferrer');
        expect(social?.target).toBe('_blank');
        expect(social?.textContent).toContain('LinkedIn');

        expect(container.textContent).toContain('© 2026 Acme');
    });

    it('lists top-level plain links once in a shared column', async () => {
        const container = await render(
            <Footer
                copyright="© 2026 Acme"
                groups={[
                    {
                        id: 'privacy',
                        kind: 'internal',
                        label: 'Privacy policy',
                        href: '/privacy-policy',
                    },
                    {
                        id: 'terms',
                        kind: 'internal',
                        label: 'Terms',
                        href: '/terms',
                    },
                ]}
            />,
        );

        const lists = container.querySelectorAll('footer ul');

        expect(lists).toHaveLength(1);
        expect(container.textContent).toContain('footer.links');
        expect(
            container.textContent?.match(/Privacy policy/g) ?? [],
        ).toHaveLength(1);
        expect(
            Array.from(lists[0].querySelectorAll('a')).map((link) =>
                link.getAttribute('href'),
            ),
        ).toEqual(['/privacy-policy', '/terms']);
    });

    it('omits the columns row when there are no groups, contact or social links', async () => {
        const container = await render(<Footer copyright="© 2026 Acme" />);

        expect(container.querySelector('ul')).toBeNull();
        expect(container.textContent).not.toContain('footer.contact');
    });
});

describe('BrandLogo', () => {
    it('renders the uploaded image as the link content', async () => {
        const container = await render(
            <BrandLogo
                image={{
                    src: '/media/logo.png',
                    alt: 'Acme',
                    width: 160,
                    height: 40,
                }}
            />,
        );

        const image = container.querySelector('a img');
        expect(image?.getAttribute('src')).toBe('/media/logo.png');
        expect(image?.getAttribute('alt')).toBe('Acme');
        expect(image?.getAttribute('width')).toBe('160');
        expect(container.querySelector('a')?.hasAttribute('aria-label')).toBe(
            false,
        );
    });

    it('falls back to the text logo without an image', async () => {
        const container = await render(<BrandLogo />);

        expect(container.querySelector('img')).toBeNull();
        expect(container.querySelector('a')?.getAttribute('aria-label')).toBe(
            'brand.name',
        );
        expect(container.textContent).toContain('brand.firstPart');
    });
});
