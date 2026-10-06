import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { Button } from './button';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        className,
    }: {
        children: ReactNode;
        href: string;
        className?: string;
    }) => (
        <a data-inertia-link="" href={href} className={className}>
            {children}
        </a>
    ),
    router: { on: () => () => {} },
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(node);
    });

    return container;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('Button href', () => {
    it('renders an in-page anchor as a plain link without an Inertia visit', async () => {
        const container = await render(
            <Button href="#contact" variant="outline">
                Contact
            </Button>,
        );

        const link = container.querySelector('a');
        expect(link?.getAttribute('href')).toBe('#contact');
        expect(link?.hasAttribute('data-inertia-link')).toBe(false);
        expect(link?.hasAttribute('download')).toBe(false);
        expect(link?.className).toContain('border-border-subtle');
    });

    it('keeps an Inertia link for paths with the same visual variant', async () => {
        const container = await render(
            <Button href="/articles" variant="outline">
                Articles
            </Button>,
        );

        const link = container.querySelector('a');
        expect(link?.getAttribute('href')).toBe('/articles');
        expect(link?.hasAttribute('data-inertia-link')).toBe(true);
        expect(link?.className).toContain('border-border-subtle');
    });

    it('opens an external href in a new tab and says so in its accessible name', async () => {
        const initialPage = {
            props: {
                i18n: {
                    area: 'admin',
                    locale: 'en',
                    defaultLocale: 'en',
                    fallback: 'en',
                    dir: 'ltr',
                    availableLocales: [],
                    messages: { a11y: { opensInNewTab: 'opens in a new tab' } },
                },
            },
        } as unknown as Page<PageProps & SharedPageProps>;

        const container = await render(
            <I18nProvider initialPage={initialPage}>
                <Button
                    href="/admin/pages/5/preview/en"
                    variant="outline"
                    external
                >
                    Preview
                </Button>
            </I18nProvider>,
        );

        const link = container.querySelector('a');
        expect(link?.getAttribute('href')).toBe('/admin/pages/5/preview/en');
        expect(link?.getAttribute('target')).toBe('_blank');
        expect(link?.getAttribute('rel')).toBe('noopener noreferrer');
        expect(link?.hasAttribute('data-inertia-link')).toBe(false);
        expect(link?.className).toContain('border-border-subtle');
        expect(link?.textContent).toBe('Preview (opens in a new tab)');
    });

    it('accepts external only together with href (type contract)', () => {
        const withoutHref = (
            // @ts-expect-error `external` is only valid together with `href`.
            <Button external>Broken</Button>
        );

        expect(withoutHref).toBeTruthy();
    });
});
