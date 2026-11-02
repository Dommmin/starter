import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { SiteLaunchPanel } from '@/components/admin/site-launch-panel';
import { I18nProvider } from '@/i18n';

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => post(...args), on: () => () => {} },
    usePage: () => ({ props: {} }),
    useForm: <T extends Record<string, unknown>>(initial: T) => ({
        data: initial,
        errors: {},
        processing: false,
        recentlySuccessful: false,
        setData: vi.fn(),
        setDefaults: vi.fn(),
        submit: vi.fn(),
    }),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const initialPage = {
    props: {
        i18n: {
            area: 'admin',
            locale: 'en',
            defaultLocale: 'en',
            messages: {},
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const roots: Root[] = [];

async function render(launch: App.Data.Admin.System.SiteLaunchData) {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    roots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <SiteLaunchPanel launch={launch} />
            </I18nProvider>,
        );
    });

    return container;
}

afterEach(async () => {
    await act(async () => roots.splice(0).forEach((root) => root.unmount()));
    document.body.replaceChildren();
    post.mockReset();
});

describe('SiteLaunchPanel', () => {
    it('labels every checklist item as done or to do in text, not colour alone', async () => {
        const container = await render({
            comingSoon: true,
            noindex: false,
            maintenance: false,
            maintenanceUntil: null,
            testMailRecipient: 'owner@acme.test',
            checklist: [
                { key: 'admin_two_factor', done: true },
                { key: 'test_mail', done: false },
                { key: 'search_console', done: false },
                { key: 'legal_pages', done: true },
            ],
        });

        const text = container.textContent ?? '';

        expect(text.match(/admin\.launch\.done/g)).toHaveLength(2);
        expect(text.match(/admin\.launch\.todo/g)).toHaveLength(2);
        expect(container.querySelectorAll('[role="switch"]')).toHaveLength(3);
    });

    it('sends the test e-mail with a POST to the system route', async () => {
        const container = await render({
            comingSoon: false,
            noindex: false,
            maintenance: false,
            maintenanceUntil: null,
            testMailRecipient: 'owner@acme.test',
            checklist: [],
        });

        const button = [...container.querySelectorAll('button')].find(
            (element) => element.textContent === 'admin.launch.sendTestMail',
        );

        await act(async () => button?.click());

        expect(post).toHaveBeenCalledWith(
            '/admin/system/test-mail',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the planned end only while maintenance is on', async () => {
        const on = await render({
            comingSoon: false,
            noindex: false,
            maintenance: true,
            maintenanceUntil: '2026-10-28T15:00:00+00:00',
            testMailRecipient: 'owner@acme.test',
            checklist: [],
        });

        expect(on.textContent).toContain('admin.launch.maintenanceUntil');
        expect(on.querySelector('select, [role="combobox"]')).not.toBeNull();

        const off = await render({
            comingSoon: false,
            noindex: false,
            maintenance: false,
            maintenanceUntil: null,
            testMailRecipient: 'owner@acme.test',
            checklist: [],
        });

        expect(off.textContent).not.toContain('admin.launch.maintenanceUntil');
    });
});
