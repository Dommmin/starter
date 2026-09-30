import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Alert } from './alert';
import { ConflictDialog } from './conflict-dialog';
import { OfflineBanner } from './offline-banner';
import { RetryPanel } from './retry-panel';

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

describe('Alert', () => {
    it('renders a named dismiss control with a 44 px target', async () => {
        const onDismiss = vi.fn();
        const container = await render(
            <Alert
                title="Saved"
                dismissLabel="Dismiss"
                onDismiss={onDismiss}
            />,
        );

        const dismiss = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Dismiss"]',
        );
        expect(dismiss?.classList.contains('size-11')).toBe(true);
        expect(dismiss?.textContent).toBe('');

        await act(async () => dismiss?.click());
        expect(onDismiss).toHaveBeenCalledTimes(1);
    });

    it('omits the dismiss control without a label', async () => {
        const container = await render(
            <Alert title="Saved" onDismiss={vi.fn()} />,
        );

        expect(container.querySelector('button')).toBeNull();
    });
});

describe('OfflineBanner', () => {
    it('announces the offline message', async () => {
        const container = await render(
            <OfflineBanner message="You are offline" />,
        );

        expect(container.querySelector('[role="status"]')?.textContent).toBe(
            'You are offline',
        );
    });
});

describe('RetryPanel', () => {
    it('disables the retry action while pending', async () => {
        const container = await render(
            <RetryPanel
                title="Could not load orders"
                retryLabel="Try again"
                onRetry={vi.fn()}
                isPending
            />,
        );

        const button = container.querySelector('button');
        expect(button?.getAttribute('aria-busy')).toBe('true');
        expect((button as HTMLButtonElement | null)?.disabled).toBe(true);
        // Only the pending spinner is shown, not the retry icon next to it.
        expect(button?.querySelectorAll('svg').length).toBe(1);
    });

    it('calls onRetry when activated', async () => {
        const onRetry = vi.fn();
        const container = await render(
            <RetryPanel
                title="Could not load orders"
                retryLabel="Try again"
                onRetry={onRetry}
            />,
        );

        await act(async () => {
            container.querySelector('button')?.click();
        });
        expect(onRetry).toHaveBeenCalledTimes(1);
    });
});

describe('ConflictDialog', () => {
    it('blocks Escape while pending and exposes both resolution actions', async () => {
        const onOpenChange = vi.fn();
        const onReload = vi.fn();
        const onOverwrite = vi.fn();
        await render(
            <ConflictDialog
                open
                onOpenChange={onOpenChange}
                title="This record changed"
                reloadLabel="Reload"
                onReload={onReload}
                overwriteLabel="Keep my changes"
                onOverwrite={onOverwrite}
                isPending
            />,
        );

        await act(async () => {
            document.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
            );
        });
        expect(onOpenChange).not.toHaveBeenCalled();

        const buttons = Array.from(document.querySelectorAll('button'));
        expect(buttons.map((button) => button.textContent)).toEqual([
            'Reload',
            'Keep my changes',
        ]);
    });
});
