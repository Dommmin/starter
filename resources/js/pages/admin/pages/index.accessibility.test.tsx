import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminPagesIndex from './index';

const getMock = vi.fn();
const visitMock = vi.fn();

let pageProps: App.Data.Admin.Pages.PageIndexData;

function makeProps(
    can: App.Data.Admin.Pages.PageAbilitiesData,
): App.Data.Admin.Pages.PageIndexData {
    return {
        items: [
            {
                id: 7,
                title: 'About us',
                slug: 'about-us',
                status: 'published',
                locale: 'en',
                locales: ['de', 'en'],
                updatedAt: '2026-09-01T10:00:00Z',
            },
            {
                id: 8,
                title: 'Impressum',
                slug: 'impressum',
                status: 'draft',
                locale: 'de',
                locales: ['de'],
                updatedAt: null,
            },
        ],
        pagination: { page: 1, totalPages: 1, total: 2, perPage: 15 },
        filters: {
            search: '',
            sort: 'updated_at',
            direction: 'desc',
            status: 'all',
            locale: 'en',
        },
        locales: {
            available: [
                { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
                { code: 'de', name: 'German', native: 'Deutsch', dir: 'ltr' },
            ],
            default: 'en',
        },
        can,
    };
}

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    router: {
        get: (...args: unknown[]) => getMock(...args),
        delete: vi.fn(),
        visit: (...args: unknown[]) => visitMock(...args),
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
                <AdminPagesIndex />
            </I18nProvider>,
        );
    });

    return container;
}

afterEach(async () => {
    getMock.mockClear();
    visitMock.mockClear();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('AdminPagesIndex', () => {
    it('links each title to its editor and marks a missing translation', async () => {
        pageProps = makeProps({ create: true, publish: true, delete: true });
        const container = await render();

        const titleLink = Array.from(container.querySelectorAll('a')).find(
            (link) => link.textContent === 'About us',
        );
        expect(titleLink?.getAttribute('href')).toBe('/admin/pages/7/edit');
        expect(container.textContent).toContain('Deutsch, English');
        expect(container.textContent).toContain(
            'admin.pages.missingTranslation',
        );
        expect(container.textContent).toContain('admin.pages.status.draft');
    });

    it('offers the create action only with the create ability', async () => {
        pageProps = makeProps({ create: true, publish: true, delete: true });
        let container = await render();

        const createLink = Array.from(container.querySelectorAll('a')).find(
            (link) => link.textContent === 'admin.pages.create',
        );
        expect(createLink?.getAttribute('href')).toBe('/admin/pages/create');
        expect(
            container.querySelectorAll(
                'button[aria-label="admin.pages.rowActionsLabel"]',
            ),
        ).toHaveLength(2);

        await act(async () => {
            mountedRoots.splice(0).forEach((root) => root.unmount());
        });
        document.body.replaceChildren();

        pageProps = makeProps({ create: false, publish: true, delete: false });
        container = await render();

        expect(container.textContent).not.toContain('admin.pages.create');
    });

    it('clears the status and content locale filters to the server defaults', async () => {
        pageProps = makeProps({ create: true, publish: true, delete: true });
        pageProps.filters = {
            ...pageProps.filters,
            status: 'draft',
            locale: 'de',
        };
        const container = await render();

        const clearButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) => button.textContent === 'admin.pages.clearFilters');

        await act(async () => {
            clearButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toMatchObject({
            status: 'all',
            locale: 'en',
            sort: 'updated_at',
            direction: 'desc',
            page: 1,
        });
    });
});
