import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { I18nProvider } from '@/i18n';
import AdminContactIndex from './index';

type IndexProps = App.Data.Admin.Contact.ContactMessageIndexData;

const getMock = vi.fn();

let pageProps: IndexProps;

function makeProps(): IndexProps {
    return {
        items: [
            {
                id: 7,
                name: 'Ada Sender',
                email: 'ada@example.test',
                locale: 'en',
                status: 'sent',
                attempts: 1,
                category: 'spam',
                createdAt: '2026-11-01T10:00:00Z',
            },
            {
                id: 8,
                name: 'Grace Sender',
                email: 'grace@example.test',
                locale: 'en',
                status: 'pending',
                attempts: 0,
                category: null,
                createdAt: '2026-11-02T10:00:00Z',
            },
        ],
        pagination: { page: 1, totalPages: 1, total: 2, perPage: 15 },
        filters: {
            search: '',
            sort: 'created_at',
            direction: 'desc',
            status: 'all',
            category: 'all',
        },
        can: { delete: false, export: false },
        exportMaxRows: 5000,
    };
}

vi.mock('@/components/admin/saved-views-menu', () => ({
    SavedViewsMenu: () => null,
}));

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
                <AdminContactIndex />
            </I18nProvider>,
        );
    });

    return container;
}

afterEach(async () => {
    getMock.mockClear();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('AdminContactIndex', () => {
    it('shows the category badge of classified messages and a dash otherwise', async () => {
        pageProps = makeProps();
        const container = await render();

        const headers = Array.from(container.querySelectorAll('th')).map(
            (header) => header.textContent,
        );
        const categoryColumn = headers.indexOf('admin.contact.fields.category');
        expect(categoryColumn).toBeGreaterThan(-1);

        const categoryCells = Array.from(
            container.querySelectorAll('tbody tr'),
        ).map((row) => row.children[categoryColumn]?.textContent);
        expect(categoryCells).toEqual(['admin.contact.categorySpam', '—']);
    });

    it('shows the active category filter and clears it to the default', async () => {
        pageProps = makeProps();
        pageProps.filters = { ...pageProps.filters, category: 'lead' };
        const container = await render();

        const categoryFilter = Array.from(
            container.querySelectorAll('[role="combobox"]'),
        ).find((combobox) =>
            combobox.textContent?.includes('admin.contact.categoryLead'),
        );
        expect(categoryFilter).toBeDefined();

        const clearButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) => button.textContent === 'admin.contact.clearFilters');

        await act(async () => {
            clearButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toMatchObject({
            category: 'all',
            status: 'all',
            page: 1,
        });
    });
});
