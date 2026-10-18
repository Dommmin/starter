import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SavedViewsMenu } from '@/components/admin/saved-views-menu';
import { I18nProvider } from '@/i18n';

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: {} }),
    router: { on: () => () => {} },
    useHttp: (data: Record<string, unknown>) => ({
        data,
        errors: {},
        processing: false,
        setData: vi.fn(),
        transform: vi.fn(),
        reset: vi.fn(),
        post,
        delete: vi.fn(),
    }),
    Link: ({ href, children }: { href: string; children: ReactNode }) => (
        <a href={href}>{children}</a>
    ),
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

async function render(): Promise<void> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <SavedViewsMenu list="admin.articles.index" />
            </I18nProvider>,
        );
    });
}

afterEach(() => {
    for (const root of mountedRoots.splice(0)) {
        act(() => root.unmount());
    }
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
});

describe('SavedViewsMenu', () => {
    it('loads nothing until opened, then lists the saved views as links', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                max: 20,
                views: [
                    {
                        id: 1,
                        name: 'My drafts',
                        url: '/admin/articles?status=draft',
                    },
                ],
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        await render();
        expect(fetchMock).not.toHaveBeenCalled();

        await act(async () => {
            document.querySelector<HTMLButtonElement>('button')!.click();
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0][0])).toContain(
            'list=admin.articles.index',
        );
        const link = document.querySelector<HTMLAnchorElement>(
            'a[href="/admin/articles?status=draft"]',
        );
        expect(link?.textContent).toBe('My drafts');
    });

    it('shows an error when the views cannot be loaded', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, json: async () => ({}) }),
        );

        await render();
        await act(async () => {
            document.querySelector<HTMLButtonElement>('button')!.click();
        });

        expect(document.body.textContent).toContain(
            'admin.savedViews.loadError',
        );
    });
});
