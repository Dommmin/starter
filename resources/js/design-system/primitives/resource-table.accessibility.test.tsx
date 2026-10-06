import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    ResourceTable,
    type ResourceListFilters,
    type ResourceTableProps,
} from './resource-table';

const getMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: { get: (...args: unknown[]) => getMock(...args) },
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

type Row = { id: number; name: string };

const mountedRoots: Root[] = [];

const defaultFilters: ResourceListFilters = {
    search: '',
    sort: 'name',
    direction: 'asc',
    status: 'all',
};

function props(
    overrides: Partial<ResourceTableProps<Row>> = {},
): ResourceTableProps<Row> {
    return {
        url: { url: '/admin/things' },
        rows: [{ id: 1, name: 'Ada' }],
        rowKey: (row) => row.id,
        pagination: { page: 3, totalPages: 5, total: 45, perPage: 10 },
        filters: defaultFilters,
        searchable: true,
        labels: {
            caption: 'Things',
            searchLabel: 'Search things',
            searchClear: 'Clear search',
            clearFilters: 'Clear filters',
            emptyTitle: 'No things yet',
            noResultsTitle: 'No matching things',
            errorTitle: 'Could not load',
            errorRetry: 'Retry',
            previousPage: 'Previous',
            nextPage: 'Next',
            paginationSummary: ({ from, to, total }) =>
                `${from}-${to} of ${total}`,
        },
        filterFields: [
            {
                name: 'status',
                label: 'Status',
                defaultValue: 'all',
                options: [
                    { value: 'all', label: 'All' },
                    { value: 'active', label: 'Active' },
                ],
            },
        ],
        columns: [
            {
                key: 'name',
                label: 'Name',
                sortable: true,
                render: (row) => row.name,
            },
            { key: 'kind', label: 'Kind', render: () => 'x' },
        ],
        rowActions: {
            label: (row) => `Actions for ${row.name}`,
            items: () => [{ id: 'noop', label: 'Noop', onSelect: () => {} }],
        },
        ...overrides,
    };
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

function lastVisit(): [
    string,
    Record<string, unknown>,
    Record<string, unknown>,
] {
    return getMock.mock.calls.at(-1) as [
        string,
        Record<string, unknown>,
        Record<string, unknown>,
    ];
}

function buttonByText(container: HTMLElement, text: string) {
    return Array.from(container.querySelectorAll('button')).find((button) =>
        button.textContent?.includes(text),
    );
}

afterEach(async () => {
    getMock.mockClear();
    vi.useRealTimers();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('ResourceTable', () => {
    it('exposes aria-sort only on sortable columns', async () => {
        const container = await render(<ResourceTable<Row> {...props()} />);

        const headers = container.querySelectorAll('th');
        expect(headers[0].getAttribute('aria-sort')).toBe('ascending');
        expect(headers[1].hasAttribute('aria-sort')).toBe(false);
        expect(
            container.querySelector('button[aria-label="Actions for Ada"]'),
        ).not.toBeNull();
    });

    it('toggles the direction and resets the page when sorting', async () => {
        const container = await render(<ResourceTable<Row> {...props()} />);

        await act(async () => {
            buttonByText(container, 'Name')?.click();
        });

        const [url, params, options] = lastVisit();
        expect(url).toBe('/admin/things');
        expect(params).toEqual({
            search: '',
            status: 'all',
            sort: 'name',
            direction: 'desc',
            page: 1,
        });
        expect(options).toMatchObject({
            preserveState: true,
            preserveScroll: true,
            replace: false,
        });
    });

    it('keeps the query and pushes history when changing page', async () => {
        const container = await render(
            <ResourceTable<Row>
                {...props({ filters: { ...defaultFilters, status: 'active' } })}
            />,
        );

        await act(async () => {
            container
                .querySelector<HTMLButtonElement>('button[aria-label="Next"]')
                ?.click();
        });

        const [, params, options] = lastVisit();
        expect(params).toMatchObject({ status: 'active', page: 4 });
        expect(options).toMatchObject({ replace: false });
    });

    it('debounces search, resets the page and replaces the history entry', async () => {
        vi.useFakeTimers();
        const container = await render(<ResourceTable<Row> {...props()} />);
        const input = container.querySelector<HTMLInputElement>(
            'input[type="search"]',
        )!;

        await act(async () => {
            Object.getOwnPropertyDescriptor(
                HTMLInputElement.prototype,
                'value',
            )!.set!.call(input, 'ada');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        expect(getMock).not.toHaveBeenCalled();

        await act(async () => {
            vi.advanceTimersByTime(300);
        });

        const [, params, options] = lastVisit();
        expect(params).toMatchObject({ search: 'ada', page: 1 });
        expect(options).toMatchObject({ replace: true });
    });

    it('distinguishes no results from an empty list and clears filters to defaults', async () => {
        const container = await render(
            <ResourceTable<Row>
                {...props({
                    rows: [],
                    filters: { ...defaultFilters, search: 'zzz' },
                })}
            />,
        );

        expect(container.textContent).toContain('No matching things');
        expect(container.textContent).not.toContain('No things yet');

        await act(async () => {
            buttonByText(container, 'Clear filters')?.click();
        });

        expect(lastVisit()[1]).toMatchObject({
            search: '',
            status: 'all',
            page: 1,
        });
    });

    it('shows the empty state without a clear action when nothing is filtered', async () => {
        const container = await render(
            <ResourceTable<Row> {...props({ rows: [] })} />,
        );

        expect(container.textContent).toContain('No things yet');
        expect(buttonByText(container, 'Clear filters')).toBeUndefined();
    });

    it('offers the empty-list call to action when there are no records', async () => {
        const container = await render(
            <ResourceTable<Row>
                {...props({
                    rows: [],
                    header: undefined,
                    emptyAction: <a href="/admin/things/create">Add thing</a>,
                })}
            />,
        );

        expect(container.textContent).toContain('No things yet');
        expect(
            container.querySelector('a[href="/admin/things/create"]')
                ?.textContent,
        ).toBe('Add thing');
        expect(container.querySelector('h1')).toBeNull();
    });

    it('hides the empty-list call to action for an empty filter result', async () => {
        const emptyAction = <a href="/admin/things/create">Add thing</a>;
        const filtered = await render(
            <ResourceTable<Row>
                {...props({
                    rows: [],
                    filters: { ...defaultFilters, status: 'active' },
                    emptyAction,
                })}
            />,
        );

        expect(filtered.textContent).toContain('No matching things');
        expect(buttonByText(filtered, 'Clear filters')).toBeDefined();
        expect(filtered.textContent).not.toContain('Add thing');

        const withoutNoResultsLabels = await render(
            <ResourceTable<Row>
                {...props({
                    rows: [],
                    filters: { ...defaultFilters, search: 'zzz' },
                    labels: { ...props().labels, noResultsTitle: undefined },
                    emptyAction,
                })}
            />,
        );

        expect(withoutNoResultsLabels.textContent).toContain('No things yet');
        expect(withoutNoResultsLabels.textContent).not.toContain('Add thing');
    });

    it('marks the table busy while a visit is in flight and shows a retry panel on failure', async () => {
        const container = await render(<ResourceTable<Row> {...props()} />);

        await act(async () => {
            buttonByText(container, 'Name')?.click();
        });
        expect(
            container.querySelector('table')?.getAttribute('aria-busy'),
        ).toBe('true');

        const [, , options] = lastVisit();
        await act(async () => {
            (options.onError as () => void)();
            (options.onFinish as () => void)();
        });

        expect(container.textContent).toContain('Could not load');
        expect(buttonByText(container, 'Retry')).toBeDefined();
    });
});
