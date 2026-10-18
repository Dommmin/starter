import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminTrashIndex from './index';

let props: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props }),
    router: { put: vi.fn(), delete: vi.fn(), on: () => () => {} },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
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

const mountedRoots: Root[] = [];

async function render(): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AdminTrashIndex />
            </I18nProvider>,
        );
    });

    return container;
}

afterEach(() => {
    for (const root of mountedRoots.splice(0)) {
        act(() => root.unmount());
    }
    document.body.innerHTML = '';
});

describe('AdminTrashIndex', () => {
    it('lists trashed records with a named action menu per row', async () => {
        props = {
            retentionDays: 30,
            items: [
                {
                    type: 'pages',
                    id: 4,
                    title: 'About us',
                    locales: ['en'],
                    deletedAt: '2026-10-10T08:00:00Z',
                    purgeAt: '2026-11-09T08:00:00Z',
                },
                {
                    type: 'articles',
                    id: 9,
                    title: null,
                    locales: [],
                    deletedAt: '2026-10-12T08:00:00Z',
                    purgeAt: '2026-11-11T08:00:00Z',
                },
            ],
        };

        const container = await render();

        expect(container.textContent).toContain('About us');
        expect(
            container.querySelectorAll('button[aria-haspopup="menu"]'),
        ).toHaveLength(2);
    });

    it('shows the empty state without records', async () => {
        props = { retentionDays: 30, items: [] };

        const container = await render();

        expect(
            container.querySelectorAll('button[aria-haspopup="menu"]'),
        ).toHaveLength(0);
        expect(container.textContent).toContain('admin.trash.emptyTitle');
    });
});
