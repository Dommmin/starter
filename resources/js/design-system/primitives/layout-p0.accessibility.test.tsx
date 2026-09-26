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
    it('keeps only one item open in single mode', async () => {
        const container = await render(
            <Accordion
                type="single"
                items={[
                    { id: 'a', trigger: 'Item A', content: 'Content A' },
                    { id: 'b', trigger: 'Item B', content: 'Content B' },
                ]}
            />,
        );

        const triggers = Array.from(container.querySelectorAll('button'));

        await act(async () => {
            triggers[0]?.click();
        });
        expect(triggers[0]?.getAttribute('aria-expanded')).toBe('true');

        await act(async () => {
            triggers[1]?.click();
        });
        expect(triggers[0]?.getAttribute('aria-expanded')).toBe('false');
        expect(triggers[1]?.getAttribute('aria-expanded')).toBe('true');
    });

    it('allows multiple open items in multiple mode', async () => {
        const container = await render(
            <Accordion
                type="multiple"
                items={[
                    { id: 'a', trigger: 'Item A', content: 'Content A' },
                    { id: 'b', trigger: 'Item B', content: 'Content B' },
                ]}
            />,
        );

        const triggers = Array.from(container.querySelectorAll('button'));

        await act(async () => {
            triggers[0]?.click();
        });
        await act(async () => {
            triggers[1]?.click();
        });

        expect(triggers[0]?.getAttribute('aria-expanded')).toBe('true');
        expect(triggers[1]?.getAttribute('aria-expanded')).toBe('true');
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
