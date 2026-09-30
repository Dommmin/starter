import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { LocaleSwitcher } from './locale-switcher';
import { ThemeSwitcher } from './theme-switcher';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { i18n: { alternateUrls: {} } } }),
    router: { on: () => () => {} },
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
            availableLocales: [
                { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
                { code: 'pl', name: 'Polish', native: 'Polski', dir: 'ltr' },
            ],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const mountedRoots: Root[] = [];

async function renderTrigger(node: ReactNode): Promise<HTMLButtonElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
        );
    });

    const trigger = container.querySelector<HTMLButtonElement>('button');
    expect(trigger).not.toBeNull();

    return trigger as HTMLButtonElement;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('ThemeSwitcher', () => {
    it('stays an icon button named by aria-label by default', async () => {
        const trigger = await renderTrigger(<ThemeSwitcher />);

        expect(trigger.getAttribute('aria-label')).toBe(
            'a11y.themeSwitcher: theme.system',
        );
        expect(trigger.textContent).toBe('');
    });

    it('is named by its visible caption in the labelled variant', async () => {
        const trigger = await renderTrigger(
            <ThemeSwitcher variant="labelled" />,
        );

        expect(trigger.hasAttribute('aria-label')).toBe(false);
        expect(trigger.textContent).toBe('theme.label: theme.system');
    });
});

describe('LocaleSwitcher', () => {
    it('stays a globe button named by aria-label by default', async () => {
        const trigger = await renderTrigger(<LocaleSwitcher />);

        expect(trigger.getAttribute('aria-label')).toBe(
            'a11y.languageSelector',
        );
    });

    it('is named by its visible caption in the labelled variant', async () => {
        const trigger = await renderTrigger(
            <LocaleSwitcher variant="labelled" />,
        );

        expect(trigger.hasAttribute('aria-label')).toBe(false);
        expect(trigger.textContent).toBe('language.label: English');
    });
});
