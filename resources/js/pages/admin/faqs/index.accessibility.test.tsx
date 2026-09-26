import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminFaqsIndex from './index';

type IndexProps = App.Data.Admin.Faqs.FaqIndexData;

const getMock = vi.fn();

let pageProps: IndexProps;

function makeProps(can: IndexProps['can']): IndexProps {
    return {
        items: [
            {
                id: 7,
                question: 'First question',
                position: 1,
                published: true,
                createdAt: '2026-09-01T10:00:00Z',
                updatedAt: '2026-09-01T10:00:00Z',
            },
            {
                id: 8,
                question: 'Second question',
                position: 2,
                published: false,
                createdAt: '2026-09-01T10:00:00Z',
                updatedAt: '2026-09-01T10:00:00Z',
            },
        ],
        pagination: { page: 1, totalPages: 1, total: 2, perPage: 15 },
        filters: {
            search: '',
            sort: 'created_at',
            direction: 'desc',
            published: 'all',
        },
        can,
    };
}

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    router: {
        get: (...args: unknown[]) => getMock(...args),
        delete: vi.fn(),
        visit: vi.fn(),
        on: () => () => {},
    },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({
        href,
        children,
        prefetch: _prefetch,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
        prefetch?: boolean;
        [key: string]: unknown;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

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

async function render(): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AdminFaqsIndex />
            </I18nProvider>,
        );
    });

    return container;
}

async function unmountAll() {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
}

afterEach(async () => {
    getMock.mockClear();
    await unmountAll();
});

describe('AdminFaqsIndex', () => {
    it('renders a captioned table whose first column links to the editor', async () => {
        pageProps = makeProps({ create: true, delete: true });
        const container = await render();

        expect(container.querySelector('table caption')?.textContent).toBe(
            'admin.faqs.tableCaption',
        );
        const editLink = Array.from(container.querySelectorAll('a')).find(
            (link) => link.textContent === 'First question',
        );
        expect(editLink?.getAttribute('href')).toBe('/admin/faqs/7/edit');
    });

    it('offers the create action and row menus only with the abilities', async () => {
        pageProps = makeProps({ create: true, delete: true });
        let container = await render();

        const createLink = Array.from(container.querySelectorAll('a')).find(
            (link) => link.textContent === 'admin.faqs.create',
        );
        expect(createLink?.getAttribute('href')).toBe('/admin/faqs/create');
        expect(
            container.querySelectorAll(
                'button[aria-label="admin.faqs.rowActionsLabel"]',
            ),
        ).toHaveLength(2);

        await unmountAll();

        pageProps = makeProps({ create: false, delete: false });
        container = await render();

        expect(container.textContent).not.toContain('admin.faqs.create');
    });

    it('clears the filters to the server defaults', async () => {
        pageProps = makeProps({ create: true, delete: true });
        pageProps.filters = {
            ...pageProps.filters,
            published: 'yes',
        };
        const container = await render();

        const clearButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) => button.textContent === 'admin.faqs.clearFilters');

        await act(async () => {
            clearButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toMatchObject({
            published: 'all',
            sort: 'created_at',
            direction: 'desc',
            page: 1,
        });
    });
});
