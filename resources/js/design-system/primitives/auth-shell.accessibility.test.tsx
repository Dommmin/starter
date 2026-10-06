import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { AuthShell } from './auth-shell';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({
        url: '/login',
        props: {
            i18n: { alternateUrls: {} },
            site: {
                name: 'Studio Test',
                isCustomized: true,
                logo: null,
                tagline: null,
                footerText: null,
                contact: { email: null, phone: null, address: null },
                social: [],
            },
        },
    }),
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

async function render(description?: string): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AuthShell title="Log in" description={description}>
                    <form aria-label="Sign in" />
                </AuthShell>
            </I18nProvider>,
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

describe('AuthShell', () => {
    it('frames the form with the site brand, the skip-link target and one h1', async () => {
        const container = await render('Enter your email');

        const header = container.querySelector('header');
        const main = container.querySelector('main#main-content');

        expect(header?.querySelector('a[href="#main-content"]')).not.toBeNull();
        expect(
            header?.querySelector('a[aria-label="Studio Test"]'),
        ).not.toBeNull();
        expect(
            header?.querySelector('button[aria-label^="a11y.themeSwitcher"]'),
        ).not.toBeNull();
        expect(container.querySelectorAll('h1')).toHaveLength(1);
        expect(main?.querySelector('h1')?.textContent).toBe('Log in');
        expect(main?.textContent).toContain('Enter your email');
        expect(
            main?.querySelector('form[aria-label="Sign in"]'),
        ).not.toBeNull();
        expect(main?.querySelectorAll('svg')).toHaveLength(0);
    });

    it('omits the description paragraph when none is given', async () => {
        const container = await render();

        expect(container.querySelectorAll('main p')).toHaveLength(0);
    });
});
