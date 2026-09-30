import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { Accordion } from './accordion';
import { Collapsible } from './collapsible';
import { Fieldset } from './fieldset';

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

describe('Collapsible', () => {
    it('toggles content visibility from the keyboard-operable trigger', async () => {
        const container = await render(
            <Collapsible trigger="Details" defaultOpen={false}>
                Hidden content
            </Collapsible>,
        );

        const trigger = container.querySelector('button');
        expect(trigger?.getAttribute('aria-expanded')).toBe('false');
        expect(trigger?.classList.contains('min-h-11')).toBe(true);

        await act(async () => {
            trigger?.click();
        });

        expect(trigger?.getAttribute('aria-expanded')).toBe('true');
        expect(container.textContent).toContain('Hidden content');
    });

    it('does not toggle when disabled', async () => {
        const container = await render(
            <Collapsible trigger="Details" disabled defaultOpen={false}>
                Hidden content
            </Collapsible>,
        );

        const trigger = container.querySelector('button');
        expect(trigger?.disabled).toBe(true);
    });
});

describe('Accordion', () => {
    const items = [
        { id: 'a', trigger: 'Item A', content: 'Content A' },
        { id: 'b', trigger: 'Item B', content: 'Content B' },
    ];

    function parts(container: HTMLElement) {
        const details = Array.from(container.querySelectorAll('details'));

        return {
            details,
            summaries: details.map(
                (item) => item.querySelector('summary') as HTMLElement,
            ),
        };
    }

    /** Browsers turn Enter/Space on a focused summary into a click. */
    async function activate(summary: HTMLElement) {
        await act(async () => {
            summary.focus();
            summary.click();
        });
    }

    it('uses native details/summary with content in the markup while closed', async () => {
        const container = await render(<Accordion items={items} />);
        const { details, summaries } = parts(container);

        expect(details).toHaveLength(2);
        expect(details.every((item) => !item.open)).toBe(true);
        expect(container.textContent).toContain('Content A');
        expect(summaries[0].getAttribute('role')).toBeNull();
        expect(
            summaries[0].querySelector('svg')?.getAttribute('aria-hidden'),
        ).toBe('true');

        summaries[0].focus();
        expect(document.activeElement).toBe(summaries[0]);
    });

    it('keeps only one item open in single mode', async () => {
        const container = await render(
            <Accordion type="single" items={items} />,
        );
        const { details, summaries } = parts(container);

        await activate(summaries[0]);
        expect(details[0].open).toBe(true);

        await activate(summaries[1]);
        expect(details[0].open).toBe(false);
        expect(details[1].open).toBe(true);

        await activate(summaries[1]);
        expect(details[1].open).toBe(false);
    });

    it('allows multiple open items in multiple mode', async () => {
        const container = await render(
            <Accordion type="multiple" items={items} defaultOpenIds={['a']} />,
        );
        const { details, summaries } = parts(container);

        expect(details[0].open).toBe(true);
        await activate(summaries[1]);

        expect(details[0].open).toBe(true);
        expect(details[1].open).toBe(true);
    });

    it('does not toggle a disabled item', async () => {
        const container = await render(
            <Accordion items={[{ ...items[0], disabled: true }]} />,
        );
        const { details, summaries } = parts(container);

        expect(summaries[0].getAttribute('aria-disabled')).toBe('true');
        await activate(summaries[0]);
        expect(details[0].open).toBe(false);
    });
});

describe('Fieldset', () => {
    it('renders a legend and disables all controls when disabled', async () => {
        const container = await render(
            <Fieldset legend="Contact details" disabled>
                <input aria-label="Email" />
            </Fieldset>,
        );

        expect(container.querySelector('legend')?.textContent).toBe(
            'Contact details',
        );
        expect(
            container.querySelector<HTMLFieldSetElement>('fieldset')?.disabled,
        ).toBe(true);
    });
});
