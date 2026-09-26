import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminAuditIndex from './index';

const getMock = vi.fn();

const props: App.Data.Admin.Audit.AuditLogIndexData = {
    items: [
        {
            id: 2,
            createdAt: '2026-09-27T10:00:00Z',
            actorName: 'Ada Admin',
            action: 'page.updated',
            subjectType: 'page',
            subjectId: 7,
            changedFields: ['en.title', 'en.body'],
        },
        {
            id: 1,
            createdAt: '2026-09-26T10:00:00Z',
            actorName: null,
            action: 'user.role_changed',
            subjectType: 'user',
            subjectId: 3,
            changedFields: [],
        },
    ],
    pagination: { page: 1, totalPages: 1, total: 2, perPage: 25 },
    filters: {
        search: '',
        sort: 'created_at',
        direction: 'desc',
        action: 'page.updated',
    },
    actions: ['page.created', 'page.updated', 'user.role_changed'],
};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props }),
    router: {
        get: (...args: unknown[]) => getMock(...args),
        on: () => () => {},
    },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string | { url: string };
        children: ReactNode;
        [key: string]: unknown;
    }) => {
        const targetHref = typeof href === 'string' ? href : href.url;
        return (
            <a href={targetHref} {...rest}>
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
});

describe('AdminAuditIndex', () => {
    it('renders a read-only table with actor, action, object and changed field names', async () => {
        const container = await render(<AdminAuditIndex />);
        const rows = Array.from(container.querySelectorAll('tbody tr'));

        expect(rows).toHaveLength(2);
        expect(rows[0].textContent).toContain('Ada Admin');
        expect(rows[0].textContent).toContain(
            'admin.audit.actions.page.updated',
        );
        expect(rows[0].textContent).toContain('admin.audit.subjectLabel');
        expect(rows[0].textContent).toContain('en.title, en.body');
        expect(rows[1].textContent).toContain('admin.audit.system');
        expect(rows[1].textContent).toContain('admin.audit.noChanges');
        expect(container.querySelector('input[type="search"]')).toBeNull();
        expect(
            container.querySelector('[aria-label^="admin.audit.rowActions"]'),
        ).toBeNull();
    });

    it('clears the action filter back to all actions', async () => {
        const container = await render(<AdminAuditIndex />);

        const clearButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) => button.textContent === 'admin.audit.clearFilters');

        await act(async () => {
            clearButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        const [, params] = getMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];
        expect(params).toMatchObject({
            action: 'all',
            sort: 'created_at',
            direction: 'desc',
            page: 1,
        });
        expect(params).not.toHaveProperty('search');
    });

    it('toggles the date sort direction', async () => {
        const container = await render(<AdminAuditIndex />);

        const dateHeaderButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) =>
            button.textContent?.includes('admin.audit.columnDate'),
        );

        await act(async () => {
            dateHeaderButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        const [, params] = getMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];
        expect(params).toMatchObject({
            sort: 'created_at',
            direction: 'asc',
            action: 'page.updated',
        });
    });
});
