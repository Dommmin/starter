import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { RecordHistory } from '@/components/admin/record-history';

let pageProps: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    router: { on: () => () => {} },
    Link: ({ children }: { children: unknown }) => <a>{children as string}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const initialPage = {
    props: {
        i18n: {
            area: 'admin',
            locale: 'en',
            defaultLocale: 'en',
            messages: {
                admin: {
                    history: {
                        entry: ':actor · :action',
                        fields: 'Changed fields: :fields',
                    },
                },
            },
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
                <RecordHistory />
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
    pageProps = {};
});

describe('RecordHistory', () => {
    it('renders nothing without a shared history', async () => {
        const container = await render();

        expect(container.textContent).toBe('');
    });

    it('lists entries with the changed field names, never values', async () => {
        pageProps = {
            recordHistory: {
                limit: 10,
                entries: [
                    {
                        id: 3,
                        createdAt: '2026-10-16T09:00:00+00:00',
                        actorName: 'Ada',
                        action: 'article.updated',
                        subjectType: 'article',
                        subjectId: 1,
                        changedFields: ['en.title', 'en.slug'],
                    },
                ],
            },
        };

        const container = await render();

        expect(container.querySelector('section h2')).not.toBeNull();
        expect(container.querySelectorAll('li')).toHaveLength(1);
        expect(container.textContent).toContain('Ada');
        expect(container.textContent).toContain('en.title, en.slug');
    });
});
