import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { CardFooter } from './card';
import { Inline } from './inline';
import { Separator } from './separator';

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

describe('Inline', () => {
    it('lays children out in a non-wrapping, centred row by default', async () => {
        const container = await render(
            <Inline>
                <span>A</span>
                <span>B</span>
            </Inline>,
        );
        const row = container.firstElementChild!;

        expect(row.classList).toContain('flex-row');
        expect(row.classList).toContain('gap-2');
        expect(row.classList).toContain('items-center');
        expect(row.classList).toContain('justify-start');
        expect(row.classList).not.toContain('flex-wrap');
    });

    it('maps gap, align, justify and wrap props to the row layout', async () => {
        const container = await render(
            <Inline gap="relaxed" align="stretch" justify="between" wrap>
                <span>A</span>
            </Inline>,
        );
        const row = container.firstElementChild!;

        expect(row.classList).toContain('gap-6');
        expect(row.classList).toContain('items-stretch');
        expect(row.classList).toContain('justify-between');
        expect(row.classList).toContain('flex-wrap');
    });

    it('stretches a vertical Separator to the row height', async () => {
        const container = await render(
            <Inline align="center">
                <span>A</span>
                <Separator orientation="vertical" />
                <span>B</span>
            </Inline>,
        );
        const separator = container.querySelector(
            '[data-orientation="vertical"]',
        )!;

        expect(separator.classList).toContain('self-stretch');
        expect(separator.classList).not.toContain('h-full');
    });

    it('is the row of CardFooter', async () => {
        const container = await render(
            <CardFooter>
                <button type="button">Save</button>
            </CardFooter>,
        );

        expect(container.querySelector('.px-6 > .flex-row')).not.toBeNull();
    });

    it('rejects arbitrary styling (ADR-019)', () => {
        void (
            (
                // @ts-expect-error ADR-019: screens cannot restyle primitives.
                <Inline className="gap-[13px]">
                    <span>A</span>
                </Inline>
            )
        );
        void (
            (
                // @ts-expect-error ADR-019: no inline styles either.
                <Inline style={{ gap: 13 }}>
                    <span>A</span>
                </Inline>
            )
        );
        void (
            (
                // @ts-expect-error Only the approved gap scale is accepted.
                <Inline gap="huge">
                    <span>A</span>
                </Inline>
            )
        );
    });
});
