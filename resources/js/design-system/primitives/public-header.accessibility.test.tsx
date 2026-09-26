import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { PublicHeader } from './public-header';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: { auth: { user: null }, i18n: { alternateUrls: {} } },
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
            <a href={targetHref} {...props}>
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
                    { id: 'features', label: 'Features', href: '#features' },
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
});
