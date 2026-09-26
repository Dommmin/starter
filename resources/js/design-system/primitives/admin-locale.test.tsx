import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { I18nProvider, useTranslation } from '@/i18n';
import type { I18nPayload } from '@/i18n/types';
import { AdminLocaleSelect } from './admin-locale-select';

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
    document.documentElement.lang = 'en';
    document.documentElement.dir = 'ltr';
});

const enPayload: I18nPayload = {
    area: 'admin',
    locale: 'en',
    defaultLocale: 'en',
    fallback: 'en',
    dir: 'ltr',
    availableLocales: [
        { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
        { code: 'pl', name: 'Polish', native: 'Polski', dir: 'ltr' },
    ],
    messages: {
        admin: {
            dashboard: 'Dashboard',
            languageUpdated: 'Language preference updated.',
        },
        settings: {
            appearance: {
                language: 'Language',
            },
        },
        errors: {
            serverError: {
                description: 'An unexpected server error occurred.',
            },
        },
    },
};

const plPayload: I18nPayload = {
    area: 'admin',
    locale: 'pl',
    defaultLocale: 'en',
    fallback: 'en',
    dir: 'ltr',
    availableLocales: [
        { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
        { code: 'pl', name: 'Polish', native: 'Polski', dir: 'ltr' },
    ],
    messages: {
        admin: {
            dashboard: 'Pulpit',
            languageUpdated: 'Preferencja języka została zaktualizowana.',
        },
        settings: {
            appearance: {
                language: 'Język',
            },
        },
        errors: {
            serverError: {
                description: 'Wystąpił błąd serwera.',
            },
        },
    },
};

function createMockPage(i18n: I18nPayload, url = '/admin') {
    return {
        component: 'admin/index',
        props: {
            i18n,
            errors: {},
        },
        url,
        version: '1',
        clearHistory: false,
        encryptHistory: false,
        preserveState: false,
        rememberedState: {},
    } as unknown as Parameters<typeof I18nProvider>[0]['initialPage'];
}

function TestConsumer() {
    const { t, locale } = useTranslation();
    return (
        <div>
            <span data-testid="current-locale">{locale}</span>
            <span data-testid="dashboard-label">{t('admin.dashboard')}</span>
        </div>
    );
}

describe('Admin Locale Switching & I18nProvider Regression', () => {
    it('updates translations and document.lang when receiving inertia:success event on same URL redirect', async () => {
        const initialPage = createMockPage(enPayload);

        const container = await render(
            <I18nProvider initialPage={initialPage}>
                <TestConsumer />
            </I18nProvider>,
        );

        expect(document.documentElement.lang).toBe('en');
        expect(
            container.querySelector('[data-testid="current-locale"]')
                ?.textContent,
        ).toBe('en');
        expect(
            container.querySelector('[data-testid="dashboard-label"]')
                ?.textContent,
        ).toBe('Dashboard');

        // Simulate an Inertia redirect back to the same URL (which emits 'inertia:success', not 'inertia:navigate')
        const updatedPage = createMockPage(plPayload);
        await act(async () => {
            document.dispatchEvent(
                new CustomEvent('inertia:success', {
                    detail: { page: updatedPage },
                }),
            );
        });

        expect(document.documentElement.lang).toBe('pl');
        expect(
            container.querySelector('[data-testid="current-locale"]')
                ?.textContent,
        ).toBe('pl');
        expect(
            container.querySelector('[data-testid="dashboard-label"]')
                ?.textContent,
        ).toBe('Pulpit');
    });

    it('updates translations when receiving inertia:navigate event across route changes', async () => {
        const initialPage = createMockPage(enPayload, '/admin');

        const container = await render(
            <I18nProvider initialPage={initialPage}>
                <TestConsumer />
            </I18nProvider>,
        );

        expect(
            container.querySelector('[data-testid="dashboard-label"]')
                ?.textContent,
        ).toBe('Dashboard');

        const navigatedPage = createMockPage(plPayload, '/admin/users');
        await act(async () => {
            document.dispatchEvent(
                new CustomEvent('inertia:navigate', {
                    detail: { page: navigatedPage },
                }),
            );
        });

        expect(document.documentElement.lang).toBe('pl');
        expect(
            container.querySelector('[data-testid="dashboard-label"]')
                ?.textContent,
        ).toBe('Pulpit');
    });

    it('AdminLocaleSelect renders select trigger and options', async () => {
        const initialPage = createMockPage(enPayload);

        const container = await render(
            <I18nProvider initialPage={initialPage}>
                <AdminLocaleSelect />
            </I18nProvider>,
        );

        const trigger = container.querySelector('#admin-locale');
        expect(trigger).toBeDefined();
        expect(trigger?.getAttribute('aria-label')).toBe('Language');
    });
});
