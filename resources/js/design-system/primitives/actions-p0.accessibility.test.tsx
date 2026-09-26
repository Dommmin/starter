import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ActionMenu } from './action-menu';
import { FormDialog } from './form-dialog';

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
});

describe('ActionMenu', () => {
    it('exposes an accessible trigger and disables it when instructed', async () => {
        const container = await render(
            <ActionMenu
                triggerLabel="Row actions"
                disabled
                items={[
                    { id: 'edit', label: 'Edit', onSelect: vi.fn() },
                    {
                        id: 'delete',
                        label: 'Delete',
                        tone: 'destructive',
                        onSelect: vi.fn(),
                    },
                ]}
            />,
        );

        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Row actions"]',
        );
        expect(trigger?.disabled).toBe(true);
    });

    it('opens the menu from its icon trigger and exposes the items', async () => {
        const onSelect = vi.fn();
        const container = await render(
            <ActionMenu
                triggerLabel="Row actions"
                items={[{ id: 'edit', label: 'Edit', onSelect }]}
            />,
        );

        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Row actions"]',
        );
        expect(trigger?.getAttribute('aria-haspopup')).toBe('menu');

        await act(async () => {
            trigger?.focus();
            trigger?.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
            );
        });

        expect(trigger?.getAttribute('aria-expanded')).toBe('true');
        const item = Array.from(
            document.querySelectorAll('[role="menuitem"]'),
        ).find((node) => node.textContent === 'Edit');
        expect(item).toBeDefined();
    });
});

describe('FormDialog', () => {
    it('prevents closing while pending and disables the cancel control', async () => {
        const onOpenChange = vi.fn();
        await render(
            <FormDialog
                open
                onOpenChange={onOpenChange}
                title="Edit user"
                submitLabel="Save"
                cancelLabel="Cancel"
                closeLabel="Close"
                onSubmit={vi.fn()}
                isPending
                error="Could not save the user"
            >
                <input aria-label="Name" />
            </FormDialog>,
        );

        const cancelButton = Array.from(
            document.querySelectorAll('button'),
        ).find((button) => button.textContent === 'Cancel');
        expect((cancelButton as HTMLButtonElement)?.disabled).toBe(true);
        expect(document.querySelector('[role="alert"]')?.textContent).toBe(
            'Could not save the user',
        );

        await act(async () => {
            document.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
            );
        });

        expect(onOpenChange).not.toHaveBeenCalled();
    });

    it('calls onSubmit when the form is submitted', async () => {
        const onSubmit = vi.fn();
        await render(
            <FormDialog
                open
                onOpenChange={vi.fn()}
                title="Edit user"
                submitLabel="Save"
                cancelLabel="Cancel"
                closeLabel="Close"
                onSubmit={onSubmit}
            >
                <input aria-label="Name" />
            </FormDialog>,
        );

        const form = document.querySelector('form');
        await act(async () => {
            form?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        });

        expect(onSubmit).toHaveBeenCalledTimes(1);
    });

    it('does not call onSubmit again while pending, even via a raw submit event (Enter key path)', async () => {
        const onSubmit = vi.fn();
        await render(
            <FormDialog
                open
                onOpenChange={vi.fn()}
                title="Edit user"
                submitLabel="Save"
                cancelLabel="Cancel"
                closeLabel="Close"
                onSubmit={onSubmit}
                isPending
            >
                <input aria-label="Name" />
            </FormDialog>,
        );

        const form = document.querySelector('form');
        await act(async () => {
            form?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        });

        expect(onSubmit).not.toHaveBeenCalled();
    });
});
