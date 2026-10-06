import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { LocaleSwitcher } from './locale-switcher';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ url: '/', props: { i18n: { alternateUrls: {} } } }),
    router: {
        on: () => () => {},
    },
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

const initialPage = {
    props: {
        i18n: {
            area: 'public',
            locale: 'pl',
            defaultLocale: 'en',
            messages: {},
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [
                { code: 'en', native: 'English' },
                { code: 'pl', native: 'Polski' },
            ],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('LocaleSwitcher', () => {
    it('shows the current language code and names the language for assistive tech', async () => {
        const container = document.createElement('div');
        document.body.append(container);
        const root = createRoot(container);
        mountedRoots.push(root);

        await act(async () => {
            root.render(
                <I18nProvider initialPage={initialPage}>
                    <LocaleSwitcher />
                </I18nProvider>,
            );
        });

        const trigger = container.querySelector('button');

        expect(trigger?.textContent).toBe('PL');
        expect(trigger?.getAttribute('aria-label')).toBe(
            'a11y.languageSelector: Polski',
        );
    });

    it('renders a closed menu trigger before loading the menu and opens it on pointer down', async () => {
        const container = document.createElement('div');
        document.body.append(container);
        const root = createRoot(container);
        mountedRoots.push(root);

        await act(async () => {
            root.render(
                <I18nProvider initialPage={initialPage}>
                    <LocaleSwitcher />
                </I18nProvider>,
            );
        });

        const trigger = container.querySelector('button');
        expect(trigger?.getAttribute('aria-haspopup')).toBe('menu');
        expect(trigger?.getAttribute('aria-expanded')).toBe('false');
        expect(trigger?.getAttribute('data-state')).toBe('closed');
        expect(document.querySelector('[role="menu"]')).toBeNull();

        await act(async () => {
            trigger?.dispatchEvent(
                new MouseEvent('pointerdown', { bubbles: true, button: 0 }),
            );
        });
        await act(async () => {
            await import('./locale-menu');
            await new Promise((resolve) => setTimeout(resolve, 0));
        });

        expect(
            container.querySelector('button')?.getAttribute('aria-expanded'),
        ).toBe('true');
        expect(
            Array.from(document.querySelectorAll('[role="menuitem"]')).map(
                (item) => item.getAttribute('href'),
            ),
        ).toEqual(['/', '/pl']);
    });
});
