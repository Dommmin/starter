import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    ResourceTable,
    type ResourceTableBulkActions,
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

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
    getMock.mockReset();
});

function bulkActions(
    onRun: (keys: string[]) => Promise<void>,
): ResourceTableBulkActions<Row> {
    return {
        label: 'Bulk actions',
        selectAllLabel: 'Select all rows',
        selectRowLabel: (row) => `Select ${row.name}`,
        selectedSummary: (count) =>
            count === 0 ? 'Nothing selected' : `${count} selected`,
        clearLabel: 'Clear selection',
        actions: [
            {
                id: 'delete',
                label: 'Delete selected',
                tone: 'destructive',
                confirm: {
                    title: 'Delete the selected things?',
                    description: (count) => `${count} things will be deleted.`,
                    confirmLabel: 'Delete permanently',
                    cancelLabel: 'Cancel',
                    closeLabel: 'Close',
                },
                onRun,
            },
        ],
    };
}

function props(
    overrides: Partial<ResourceTableProps<Row>> = {},
): ResourceTableProps<Row> {
    return {
        url: { url: '/admin/things' },
        rows: [
            { id: 1, name: 'Ada' },
            { id: 2, name: 'Grace' },
            { id: 3, name: 'Linus' },
        ],
        rowKey: (row) => row.id,
        pagination: { page: 1, totalPages: 2, total: 6, perPage: 3 },
        filters: { search: '', sort: 'name', direction: 'asc' },
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
        columns: [{ key: 'name', label: 'Name', render: (row) => row.name }],
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

function checkbox(name: string): HTMLButtonElement {
    const box = document.querySelector<HTMLButtonElement>(
        `button[role="checkbox"][aria-label="${name}"]`,
    );

    if (!box) {
        throw new Error(`Checkbox "${name}" not rendered.`);
    }

    return box;
}

function buttonByText(text: string): HTMLButtonElement | undefined {
    return Array.from(document.querySelectorAll('button')).find(
        (button) => button.textContent?.trim() === text,
    );
}

function expectNothingSelected(container: HTMLElement): void {
    const toolbar = container.querySelector('[role="toolbar"]');

    expect(toolbar?.getAttribute('aria-label')).toBe('Bulk actions');
    expect(toolbar?.querySelector('[role="status"]')?.textContent).toBe(
        'Nothing selected',
    );
    expect(buttonByText('Delete selected')?.disabled).toBe(true);
}

async function click(element: HTMLElement | undefined): Promise<void> {
    await act(async () => {
        element?.click();
    });
}

describe('ResourceTable bulk actions', () => {
    it('renders no selection without bulk actions', async () => {
        const container = await render(<ResourceTable<Row> {...props()} />);

        expect(container.querySelector('button[role="checkbox"]')).toBeNull();
        expect(container.querySelector('[role="toolbar"]')).toBeNull();
    });

    it('selects rows, announces the count and drives the tri-state header', async () => {
        const container = await render(
            <ResourceTable<Row>
                {...props({ bulkActions: bulkActions(vi.fn()) })}
            />,
        );

        expectNothingSelected(container);

        await click(checkbox('Select Grace'));

        const toolbar = container.querySelector('[role="toolbar"]');
        expect(toolbar?.getAttribute('aria-label')).toBe('Bulk actions');
        expect(container.querySelector('[role="status"]')?.textContent).toBe(
            '1 selected',
        );
        expect(checkbox('Select Grace').getAttribute('aria-checked')).toBe(
            'true',
        );
        expect(checkbox('Select all rows').getAttribute('aria-checked')).toBe(
            'mixed',
        );
        expect(
            checkbox('Select Grace')
                .closest('tr')
                ?.hasAttribute('data-selected'),
        ).toBe(true);

        await click(checkbox('Select all rows'));
        expect(container.querySelector('[role="status"]')?.textContent).toBe(
            '3 selected',
        );
        expect(checkbox('Select all rows').getAttribute('aria-checked')).toBe(
            'true',
        );

        await click(buttonByText('Clear selection'));
        expectNothingSelected(container);
        expect(checkbox('Select all rows').getAttribute('aria-checked')).toBe(
            'false',
        );
    });

    it('confirms the action, passes the selected keys and clears the selection after success', async () => {
        const onRun = vi.fn(() => Promise.resolve());
        const container = await render(
            <ResourceTable<Row>
                {...props({ bulkActions: bulkActions(onRun) })}
            />,
        );

        await click(checkbox('Select Ada'));
        await click(checkbox('Select Linus'));
        await click(buttonByText('Delete selected'));

        const dialog = document.querySelector(
            '[role="alertdialog"], [role="dialog"]',
        );
        expect(dialog?.textContent).toContain('Delete the selected things?');
        expect(dialog?.textContent).toContain('2 things will be deleted.');
        expect(onRun).not.toHaveBeenCalled();

        await click(buttonByText('Delete permanently'));

        expect(onRun).toHaveBeenCalledWith(['1', '3']);
        expect(
            document.querySelector('[role="alertdialog"], [role="dialog"]'),
        ).toBeNull();
        expectNothingSelected(container);
    });

    it('keeps the dialog and the selection when the action fails', async () => {
        const onRun = vi.fn(() => Promise.reject(new Error('forbidden')));
        const container = await render(
            <ResourceTable<Row>
                {...props({ bulkActions: bulkActions(onRun) })}
            />,
        );

        await click(checkbox('Select Ada'));
        await click(buttonByText('Delete selected'));
        await click(buttonByText('Delete permanently'));

        expect(onRun).toHaveBeenCalledOnce();
        expect(
            document.querySelector('[role="alertdialog"], [role="dialog"]'),
        ).not.toBeNull();
        expect(container.querySelector('[role="status"]')?.textContent).toBe(
            '1 selected',
        );
    });

    it('clears the selection when the list moves to another page', async () => {
        const container = await render(
            <ResourceTable<Row>
                {...props({ bulkActions: bulkActions(vi.fn()) })}
            />,
        );

        await click(checkbox('Select Ada'));
        await click(
            container.querySelector<HTMLButtonElement>(
                'button[aria-label="Next"]',
            ) ?? undefined,
        );

        expect(getMock).toHaveBeenCalledOnce();
        expectNothingSelected(container);
        expect(checkbox('Select all rows').getAttribute('aria-checked')).toBe(
            'false',
        );
    });
});
