import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminUsersIndex from './index';

const getMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            items: [
                {
                    id: 1,
                    name: 'Ada Lovelace',
                    email: 'ada@example.com',
                    role: 'editor',
                    verified: true,
                    isSelf: false,
                    createdAt: '2026-01-01T00:00:00Z',
                },
            ],
            pagination: { page: 1, totalPages: 1, total: 1, perPage: 10 },
            filters: {
                search: 'ada',
                verified: 'verified',
                sort: 'created_at',
                direction: 'desc',
            },
            can: { create: true },
        },
    }),
    router: {
        get: (...args: unknown[]) => getMock(...args),
        visit: vi.fn(),
        delete: vi.fn(),
        on: () => () => {},
    },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
        [key: string]: unknown;
    }) => {
        const targetHref = typeof href === 'string' ? href : href.url;
        return (
            <a href={targetHref} {...props}>
                {children}
            </a>
        );
    },
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

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
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
    vi.unstubAllGlobals();
});

describe('AdminUsersIndex', () => {
    it('shows the e-mail on the mobile card, where admins tell users apart', async () => {
        vi.stubGlobal(
            'matchMedia',
            vi.fn((query: string) => ({
                matches: true,
                media: query,
                addEventListener: vi.fn(),
                removeEventListener: vi.fn(),
            })),
        );
        const container = await render(<AdminUsersIndex />);

        const card = container.querySelector('li > article');
        expect(card).not.toBeNull();
        expect(card?.querySelector('dd')?.textContent).toBe('ada@example.com');
    });

    it('clears search and verified filters back to their defaults', async () => {
        const container = await render(<AdminUsersIndex />);

        const clearButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) => button.textContent === 'admin.users.clearFilters');

        await act(async () => {
            clearButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        const [, params] = getMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];
        expect(params).toMatchObject({
            search: '',
            verified: 'all',
            sort: 'created_at',
            direction: 'desc',
            page: 1,
        });
    });

    it('toggles the sort direction and serializes it into the request', async () => {
        const container = await render(<AdminUsersIndex />);

        const nameHeaderButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) =>
            button.textContent?.includes('admin.users.columnName'),
        );

        await act(async () => {
            nameHeaderButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        const [, params] = getMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];
        expect(params).toMatchObject({
            sort: 'name',
            direction: 'asc',
            page: 1,
        });
    });

    it('serializes a new search term into the request after the debounce', async () => {
        vi.useFakeTimers();
        const container = await render(<AdminUsersIndex />);

        const searchInput = container.querySelector<HTMLInputElement>(
            'input[type="search"]',
        )!;
        const setNativeValue = (element: HTMLInputElement, next: string) => {
            Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype,
                'value',
            )!.set!.call(element, next);
        };

        await act(async () => {
            setNativeValue(searchInput, 'grace');
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        });

        await act(async () => {
            vi.advanceTimersByTime(300);
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        const [, params] = getMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];
        expect(params).toMatchObject({
            search: 'grace',
            verified: 'verified',
            page: 1,
        });

        vi.useRealTimers();
    });
});
