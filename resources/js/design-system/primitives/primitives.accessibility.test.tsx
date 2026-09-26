import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ConfirmDialog } from './confirm-dialog';
import { DataTable } from './data-table';
import { PasswordField } from './password-field';

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

describe('PasswordField', () => {
    it('allows keyboard users to reveal a password and announces an error', async () => {
        const container = await render(
            <PasswordField
                name="password"
                label="Password"
                value="secret"
                onChange={vi.fn()}
                showPasswordLabel="Show password"
                hidePasswordLabel="Hide password"
                error="Incorrect password"
            />,
        );

        const input = container.querySelector('input');
        const toggle = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Show password"]',
        );

        expect(input?.type).toBe('password');
        expect(toggle?.tabIndex).toBe(0);
        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'Incorrect password',
        );

        await act(async () => {
            toggle?.click();
        });

        expect(input?.type).toBe('text');
    });
});

describe('ConfirmDialog', () => {
    it('prevents dismissing a pending action with Escape', async () => {
        const onOpenChange = vi.fn();

        await render(
            <ConfirmDialog
                open
                onOpenChange={onOpenChange}
                title="Delete record"
                description="This cannot be undone."
                confirmLabel="Delete"
                cancelLabel="Cancel"
                closeLabel="Close"
                onConfirm={vi.fn()}
                isPending
            />,
        );

        await act(async () => {
            document.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
            );
        });

        expect(onOpenChange).not.toHaveBeenCalled();
        expect(
            document.querySelector<HTMLButtonElement>(
                'button[aria-label="Close"]',
            )?.disabled,
        ).toBe(true);
    });
});

describe('DataTable', () => {
    it('exposes loading and error state to assistive technology', async () => {
        const loadingContainer = await render(
            <DataTable
                caption="Users"
                columns={[
                    {
                        key: 'email',
                        header: 'Email',
                        render: () => 'test@example.com',
                    },
                ]}
                rows={[]}
                rowKey={() => 'user-1'}
                isLoading
                emptyState="No users"
            />,
        );

        expect(
            loadingContainer.querySelector('table')?.getAttribute('aria-busy'),
        ).toBe('true');

        const errorContainer = await render(
            <DataTable
                caption="Users"
                columns={[
                    {
                        key: 'email',
                        header: 'Email',
                        render: () => 'test@example.com',
                    },
                ]}
                rows={[]}
                rowKey={() => 'user-1'}
                error="Could not load users"
                emptyState="No users"
            />,
        );

        expect(
            errorContainer.querySelector('[role="alert"]')?.textContent,
        ).toBe('Could not load users');
    });
});
