import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AuthSimpleLayout from '@/layouts/auth/auth-simple-layout';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: {} }),
    router: { on: () => () => {} },
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
        [key: string]: unknown;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

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

const mountedRoots: Root[] = [];

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('AuthSimpleLayout', () => {
    it('is the skip-link target and adds no second brand link next to the header logo', async () => {
        const container = document.createElement('div');
        document.body.append(container);
        const root = createRoot(container);
        mountedRoots.push(root);

        await act(async () => {
            root.render(
                <I18nProvider initialPage={initialPage}>
                    <AuthSimpleLayout>
                        <p>form</p>
                    </AuthSimpleLayout>
                </I18nProvider>,
            );
        });

        expect(container.querySelector('main')?.id).toBe('main-content');
        expect(container.querySelector('a')).toBeNull();
    });
});
