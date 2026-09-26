import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { Maintenance } from './maintenance';
import { NotFound } from './not-found';
import { PermissionDenied } from './permission-denied';
import { SessionExpired } from './session-expired';

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

describe('status pages', () => {
    it('SessionExpired renders its title, description and action', async () => {
        const container = await render(
            <SessionExpired
                title="Your session expired"
                description="Please log in again"
                action={<button type="button">Log in</button>}
            />,
        );

        expect(container.textContent).toContain('Your session expired');
        expect(container.textContent).toContain('Please log in again');
        expect(container.querySelector('button')?.textContent).toBe('Log in');
    });

    it('PermissionDenied renders its title', async () => {
        const container = await render(
            <PermissionDenied title="You do not have access" />,
        );
        expect(container.textContent).toContain('You do not have access');
    });

    it('NotFound renders its title', async () => {
        const container = await render(<NotFound title="Page not found" />);
        expect(container.textContent).toContain('Page not found');
    });

    it('Maintenance renders its title', async () => {
        const container = await render(
            <Maintenance title="We'll be right back" />,
        );
        expect(container.textContent).toContain("We'll be right back");
    });
});
