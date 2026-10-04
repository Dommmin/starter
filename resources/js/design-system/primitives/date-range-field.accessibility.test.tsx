import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DateRangeField } from './date-range-field';
import { ResourceTable, type ResourceTableProps } from './resource-table';

const getMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: { get: (...args: unknown[]) => getMock(...args) },
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

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

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
    getMock.mockReset();
});

async function typeDate(input: HTMLInputElement, value: string): Promise<void> {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value',
        )!.set!.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

function dateInput(container: HTMLElement, name: string): HTMLInputElement {
    const input = container.querySelector<HTMLInputElement>(
        `input[type="date"][name="${name}"]`,
    );

    if (!input) {
        throw new Error(`Date input ${name} not rendered.`);
    }

    return input;
}

describe('DateRangeField', () => {
    it('groups two labelled date inputs under a legend and links their bounds', async () => {
        const container = await render(
            <DateRangeField
                name="created"
                label="Received"
                labels={{ from: 'From', to: 'To' }}
                value={{ from: '2026-10-01', to: '2026-10-03' }}
                onChange={vi.fn()}
                description="Inclusive"
                min="2026-01-01"
            />,
        );

        const fieldset = container.querySelector('fieldset');
        expect(fieldset?.querySelector('legend')?.textContent).toBe('Received');
        expect(
            document.getElementById(
                fieldset?.getAttribute('aria-describedby') ?? '',
            )?.textContent,
        ).toBe('Inclusive');

        const from = dateInput(container, 'created_from');
        const to = dateInput(container, 'created_to');
        expect(
            container.querySelector(`label[for="${from.id}"]`)?.textContent,
        ).toBe('From');
        expect(
            container.querySelector(`label[for="${to.id}"]`)?.textContent,
        ).toBe('To');
        expect(from.min).toBe('2026-01-01');
        expect(from.max).toBe('2026-10-03');
        expect(to.min).toBe('2026-10-01');
    });

    it('reports each bound change and shows errors next to their input', async () => {
        const onChange = vi.fn();
        const container = await render(
            <DateRangeField
                name="created"
                label="Received"
                labels={{ from: 'From', to: 'To' }}
                value={{ from: '', to: '' }}
                onChange={onChange}
                errors={{ to: 'The end must not precede the start.' }}
            />,
        );

        await typeDate(dateInput(container, 'created_from'), '2026-10-02');
        expect(onChange).toHaveBeenLastCalledWith({
            from: '2026-10-02',
            to: '',
        });

        const to = dateInput(container, 'created_to');
        expect(to.getAttribute('aria-invalid')).toBe('true');
        expect(
            (to.getAttribute('aria-describedby') ?? '')
                .split(' ')
                .map((id) => document.getElementById(id)?.textContent),
        ).toContain('The end must not precede the start.');
        expect(
            dateInput(container, 'created_from').hasAttribute('aria-invalid'),
        ).toBe(false);
    });
});

type Row = { id: number; name: string };

function tableProps(
    filters: Record<string, string> = {},
): ResourceTableProps<Row> {
    return {
        url: { url: '/admin/things' },
        rows: [{ id: 1, name: 'Ada' }],
        rowKey: (row) => row.id,
        pagination: { page: 2, totalPages: 3, total: 9, perPage: 3 },
        filters: { search: '', sort: 'name', direction: 'asc', ...filters },
        labels: {
            caption: 'Things',
            clearFilters: 'Clear filters',
            emptyTitle: 'No things yet',
            errorTitle: 'Could not load',
            errorRetry: 'Retry',
            previousPage: 'Previous',
            nextPage: 'Next',
            paginationSummary: ({ from, to, total }) =>
                `${from}-${to} of ${total}`,
        },
        filterFields: [
            {
                kind: 'dateRange',
                name: 'created',
                label: 'Received',
                labels: { from: 'From', to: 'To' },
            },
        ],
        columns: [{ key: 'name', label: 'Name', render: (row) => row.name }],
    };
}

function lastVisitParams(): Record<string, unknown> {
    return (getMock.mock.calls.at(-1) as [string, Record<string, unknown>])[1];
}

describe('ResourceTable date range filter', () => {
    it('sends both bounds and resets the page', async () => {
        const container = await render(
            <ResourceTable<Row> {...tableProps()} />,
        );

        await typeDate(dateInput(container, 'created_from'), '2026-10-01');

        expect(lastVisitParams()).toMatchObject({
            created_from: '2026-10-01',
            created_to: '',
            page: 1,
        });
    });

    it('swaps an inverted range typed past the input limits', async () => {
        const container = await render(
            <ResourceTable<Row>
                {...tableProps({ created_from: '2026-10-05', created_to: '' })}
            />,
        );

        await typeDate(dateInput(container, 'created_to'), '2026-10-01');

        expect(lastVisitParams()).toMatchObject({
            created_from: '2026-10-01',
            created_to: '2026-10-05',
        });
    });

    it('counts as an active filter that "clear filters" empties', async () => {
        await render(
            <ResourceTable<Row>
                {...tableProps({
                    created_from: '2026-10-01',
                    created_to: '2026-10-03',
                })}
            />,
        );

        const clear = Array.from(document.querySelectorAll('button')).find(
            (button) => button.textContent === 'Clear filters',
        );

        await act(async () => {
            clear?.click();
        });

        expect(lastVisitParams()).toMatchObject({
            created_from: '',
            created_to: '',
        });
    });
});
