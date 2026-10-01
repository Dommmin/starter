import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Button } from './button';
import { Dialog } from './dialog';
import { QrCode } from './qr-code';

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

async function settle() {
    // Radix moves and restores focus in timeouts.
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 20));
    });
}

function pressEscape() {
    document.activeElement?.dispatchEvent(
        new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
    );
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

function DialogWithTrigger() {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button onClick={() => setOpen(true)}>Connect</Button>
            <Dialog
                open={open}
                onOpenChange={setOpen}
                title="Connect a device"
                description="Two steps."
                closeLabel="Close"
                actions={<Button onClick={() => setOpen(false)}>Done</Button>}
            >
                <p>Step one</p>
            </Dialog>
        </>
    );
}

describe('Dialog', () => {
    it('is labelled by its title and description and renders the actions', async () => {
        await render(
            <Dialog
                open
                onOpenChange={vi.fn()}
                title="Connect a device"
                description="Two steps."
                closeLabel="Close"
                actions={<Button>Continue</Button>}
            >
                <p>Body</p>
            </Dialog>,
        );

        const dialog = document.querySelector('[role="dialog"]');
        const titleId = dialog?.getAttribute('aria-labelledby') ?? '';
        const descriptionId = dialog?.getAttribute('aria-describedby') ?? '';
        expect(document.getElementById(titleId)?.textContent).toBe(
            'Connect a device',
        );
        expect(document.getElementById(descriptionId)?.textContent).toBe(
            'Two steps.',
        );
        expect(dialog?.querySelector('form')).toBeNull();
        expect(
            Array.from(dialog?.querySelectorAll('button') ?? []).map(
                (button) =>
                    button.textContent || button.getAttribute('aria-label'),
            ),
        ).toEqual(['Continue', 'Close']);
    });

    it('traps focus and returns it to the trigger on Escape', async () => {
        const container = await render(<DialogWithTrigger />);
        const trigger = container.querySelector('button');

        await act(async () => {
            trigger?.focus();
            trigger?.click();
        });
        await settle();

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog?.contains(document.activeElement)).toBe(true);

        await act(async () => {
            pressEscape();
        });
        await settle();

        expect(document.querySelector('[role="dialog"]')).toBeNull();
        expect(document.activeElement).toBe(trigger);
    });

    it('keeps itself open and disables the close control while pending', async () => {
        const onOpenChange = vi.fn();
        await render(
            <Dialog
                open
                onOpenChange={onOpenChange}
                title="Connect a device"
                closeLabel="Close"
                isPending
            >
                <p>Body</p>
            </Dialog>,
        );
        await settle();

        const close = document.querySelector<HTMLButtonElement>(
            'button[aria-label="Close"]',
        );
        expect(close?.disabled).toBe(true);

        await act(async () => {
            pressEscape();
        });

        expect(onOpenChange).not.toHaveBeenCalled();
        expect(document.querySelector('[role="dialog"]')).not.toBeNull();
    });
});

describe('QrCode', () => {
    it('renders the backend SVG as a labelled image, never as markup', async () => {
        const svg =
            '<svg xmlns="http://www.w3.org/2000/svg"><script>x</script></svg>';
        const container = await render(<QrCode svg={svg} label="QR code" />);

        const image = container.querySelector('img');
        expect(image?.getAttribute('alt')).toBe('QR code');
        expect(image?.getAttribute('src')).toBe(
            `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`,
        );
        expect(container.querySelector('svg, script')).toBeNull();
    });
});
