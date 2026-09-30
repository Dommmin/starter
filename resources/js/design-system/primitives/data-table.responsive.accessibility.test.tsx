import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ActionMenu } from './action-menu';
import { DataTable, type DataTableColumn } from './data-table';
import { ResourceTable } from './resource-table';

const getMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: { get: (...args: unknown[]) => getMock(...args) },
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

type Row = { id: number; title: string; email: string; status: string };

const rows: Row[] = [
    {
        id: 1,
        title: 'Draft / ideas with a rather long title',
        email: 'very.long.address@example.com',
        status: 'Draft',
    },
];

const mountedRoots: Root[] = [];

/** Emulates a viewport below `md` (card layout) or at/above it (table). */
function setViewport(isNarrow: boolean) {
    vi.stubGlobal(
        'matchMedia',
        vi.fn((query: string) => ({
            matches: isNarrow,
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        })),
    );
}

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

function prioritizedColumns(): DataTableColumn<Row>[] {
    return [
        {
            key: 'title',
            header: 'Title',
            priority: 'primary',
            render: (row) => <a href={`/rows/${row.id}`}>{row.title}</a>,
        },
        {
            key: 'status',
            header: 'Status',
            priority: 'status',
            render: (row) => <span>{row.status}</span>,
        },
        {
            key: 'email',
            header: 'Email',
            priority: 'secondary',
            render: (row) => row.email,
        },
        {
            key: 'locales',
            header: 'Locales',
            priority: 'optional',
            render: () => 'pl, en',
        },
        {
            key: 'actions',
            header: '',
            priority: 'actions',
            render: (row) => (
                <ActionMenu
                    triggerLabel={`Actions for ${row.title}`}
                    items={[{ id: 'edit', label: 'Edit', onSelect: vi.fn() }]}
                />
            ),
        },
    ];
}

beforeEach(() => {
    getMock.mockReset();
});

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
    vi.unstubAllGlobals();
});

describe('DataTable card layout', () => {
    it('renders a labelled list with title, status, actions and secondary pairs but hides optional columns', async () => {
        setViewport(true);
        const container = await render(
            <DataTable<Row>
                caption="Articles"
                columns={prioritizedColumns()}
                rows={rows}
                rowKey={(row) => row.id}
                emptyState="Empty"
            />,
        );

        expect(container.querySelector('table')).toBeNull();
        const list = container.querySelector('ul[aria-label="Articles"]');
        expect(list).not.toBeNull();

        const card = list?.querySelector('li > article');
        const titleId = card?.getAttribute('aria-labelledby') ?? '';
        expect(document.getElementById(titleId)?.textContent).toBe(
            'Draft / ideas with a rather long title',
        );
        expect(card?.querySelector('a[href="/rows/1"]')).not.toBeNull();
        expect(card?.textContent).toContain('Draft');
        expect(card?.querySelector('dt')?.textContent).toBe('Email');
        expect(card?.querySelector('dd')?.textContent).toBe(
            'very.long.address@example.com',
        );
        expect(card?.textContent).not.toContain('Locales');
        expect(card?.textContent).not.toContain('pl, en');
    });

    it('opens the card action menu from the keyboard', async () => {
        setViewport(true);
        const container = await render(
            <DataTable<Row>
                caption="Articles"
                columns={prioritizedColumns()}
                rows={rows}
                rowKey={(row) => row.id}
                emptyState="Empty"
            />,
        );

        const trigger = container.querySelector<HTMLButtonElement>(
            'article button[aria-label="Actions for Draft / ideas with a rather long title"]',
        );
        expect(trigger).not.toBeNull();

        await act(async () => {
            trigger?.focus();
            trigger?.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
            );
        });

        expect(trigger?.getAttribute('aria-expanded')).toBe('true');
        expect(
            Array.from(document.querySelectorAll('[role="menuitem"]')).some(
                (item) => item.textContent === 'Edit',
            ),
        ).toBe(true);
    });

    it('keeps the plain table on narrow viewports when no column sets a priority', async () => {
        setViewport(true);
        const container = await render(
            <DataTable<Row>
                caption="Articles"
                columns={prioritizedColumns().map(
                    ({ priority: _priority, ...column }) => column,
                )}
                rows={rows}
                rowKey={(row) => row.id}
                emptyState="Empty"
            />,
        );

        expect(container.querySelector('ul')).toBeNull();
        expect(container.querySelector('table caption')?.textContent).toBe(
            'Articles',
        );
        expect(container.querySelectorAll('tbody td')).toHaveLength(5);
    });

    it('renders the table on wide viewports and keeps optional columns in it', async () => {
        setViewport(false);
        const container = await render(
            <DataTable<Row>
                caption="Articles"
                columns={prioritizedColumns()}
                rows={rows}
                rowKey={(row) => row.id}
                emptyState="Empty"
            />,
        );

        expect(container.querySelector('ul')).toBeNull();
        expect(
            Array.from(container.querySelectorAll('th')).map(
                (cell) => cell.textContent,
            ),
        ).toEqual(['Title', 'Status', 'Email', 'Locales', '']);
    });
});

describe('ResourceTable card layout', () => {
    it('offers sorting through a labelled select and keeps pagination and the row menu', async () => {
        setViewport(true);
        const container = await render(
            <ResourceTable<Row>
                url="/admin/articles"
                rows={rows}
                rowKey={(row) => row.id}
                pagination={{ page: 1, totalPages: 2, total: 11, perPage: 10 }}
                filters={{ search: '', sort: 'title', direction: 'asc' }}
                labels={{
                    caption: 'Articles',
                    sort: {
                        label: 'Sort by',
                        option: (column, direction) =>
                            `${column} (${direction})`,
                    },
                    clearFilters: 'Clear filters',
                    emptyTitle: 'No articles',
                    errorTitle: 'Could not load',
                    errorRetry: 'Retry',
                    previousPage: 'Previous',
                    nextPage: 'Next',
                    paginationSummary: ({ from, to, total }) =>
                        `${from}-${to} of ${total}`,
                }}
                columns={[
                    {
                        key: 'title',
                        label: 'Title',
                        sortable: true,
                        priority: 'primary',
                        render: (row) => row.title,
                    },
                    {
                        key: 'status',
                        label: 'Status',
                        priority: 'status',
                        render: (row) => row.status,
                    },
                ]}
                rowActions={{
                    label: (row) => `Actions for ${row.title}`,
                    items: () => [
                        { id: 'edit', label: 'Edit', onSelect: vi.fn() },
                    ],
                }}
            />,
        );

        const sortTrigger = Array.from(
            container.querySelectorAll<HTMLButtonElement>(
                'button[role="combobox"]',
            ),
        ).find((button) => {
            const labelId = button.getAttribute('aria-labelledby') ?? '';

            return document.getElementById(labelId)?.textContent === 'Sort by';
        });
        expect(sortTrigger?.textContent).toContain('Title (asc)');

        expect(
            container.querySelector(
                'article button[aria-label="Actions for Draft / ideas with a rather long title"]',
            ),
        ).not.toBeNull();
        expect(container.textContent).toContain('1-10 of 11');
        expect(
            container.querySelector('button[aria-label="Next"]'),
        ).not.toBeNull();

        await act(async () => {
            sortTrigger?.focus();
            sortTrigger?.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
            );
        });
        const descending = Array.from(
            document.querySelectorAll<HTMLElement>('[role="option"]'),
        ).find((option) => option.textContent === 'Title (desc)');
        expect(descending).toBeDefined();

        await act(async () => {
            descending?.focus();
            descending?.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
            );
        });

        expect(getMock).toHaveBeenCalledWith(
            '/admin/articles',
            expect.objectContaining({
                sort: 'title',
                direction: 'desc',
                page: 1,
            }),
            expect.anything(),
        );
    });
});
