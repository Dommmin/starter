import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { I18nProvider } from '@/i18n';
import { Footer } from './footer';
import { MobileNav } from './mobile-nav';

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

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
                    { id: 'pricing', label: 'Pricing', href: '/pricing' },
                    { id: 'about', label: 'About', href: '/about' },
                ]}
            />,
        );

        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Open menu"]',
        );

        await act(async () => {
            trigger?.click();
        });

        const nav = document.querySelector('nav[aria-label="Menu"]');
        expect(nav?.textContent).toContain('Pricing');
        expect(nav?.textContent).toContain('About');
        expect(
            document.querySelector('button[aria-label="Close menu"]'),
        ).not.toBeNull();
    });
});

describe('Footer', () => {
    it('renders link groups and the copyright line', async () => {
        const container = await render(
            <Footer
                copyright="© 2026 Acme"
                groups={[
                    {
                        id: 'product',
                        title: 'Product',
                        links: [
                            {
                                id: 'pricing',
                                label: 'Pricing',
                                href: '/pricing',
                            },
                        ],
                    },
                ]}
            />,
        );

        expect(container.textContent).toContain('Product');
        expect(container.textContent).toContain('Pricing');
        expect(container.textContent).toContain('© 2026 Acme');
    });
});
