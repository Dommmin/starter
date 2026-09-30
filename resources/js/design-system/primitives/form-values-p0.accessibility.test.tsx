import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { RadioGroupField } from './radio-group-field';
import { SelectField } from './select-field';
import { SwitchField } from './switch-field';
import { Tabs } from './tabs';

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

describe('RadioGroupField', () => {
    it('selects an option via keyboard and announces the error', async () => {
        const onChange = vi.fn();
        const container = await render(
            <RadioGroupField
                name="plan"
                label="Plan"
                value="basic"
                onChange={onChange}
                error="Choose a plan"
                options={[
                    { value: 'basic', label: 'Basic' },
                    { value: 'pro', label: 'Pro' },
                ]}
            />,
        );

        const items = container.querySelectorAll('button[role="radio"]');
        expect(items).toHaveLength(2);
        expect(items[0]?.getAttribute('aria-checked')).toBe('true');

        await act(async () => {
            (items[1] as HTMLButtonElement).click();
        });

        expect(onChange).toHaveBeenCalledWith('pro');
        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'Choose a plan',
        );
    });
});

describe('SwitchField', () => {
    it('toggles checked state and stays disabled when instructed', async () => {
        const onChange = vi.fn();
        const container = await render(
            <SwitchField
                name="notifications"
                label="Email notifications"
                checked={false}
                onChange={onChange}
                disabled
            />,
        );

        const toggle = container.querySelector<HTMLButtonElement>(
            'button[role="switch"]',
        );
        expect(toggle?.disabled).toBe(true);
        expect(toggle?.getAttribute('aria-checked')).toBe('false');
    });
});

/** Text of the elements an `aria-labelledby` attribute points to. */
function labelledByText(element: Element | null): string | undefined {
    return element
        ?.getAttribute('aria-labelledby')
        ?.split(' ')
        .map((id) => document.getElementById(id)?.textContent ?? '')
        .join(' ');
}

describe('Accessible names of custom controls', () => {
    it('names the switch after its visible label', async () => {
        const container = await render(
            <SwitchField
                name="enabled-3"
                label="Show “FAQ” on the home page"
                checked
                onChange={vi.fn()}
            />,
        );

        expect(
            labelledByText(container.querySelector('button[role="switch"]')),
        ).toBe('Show “FAQ” on the home page');
    });

    it('names the select trigger after its visible label, not its value', async () => {
        const container = await render(
            <SelectField
                name="role"
                label="Role"
                required
                value="editor"
                onChange={vi.fn()}
                options={[
                    { value: 'editor', label: 'Editor' },
                    { value: 'admin', label: 'Administrator' },
                ]}
            />,
        );

        const trigger = container.querySelector('button[role="combobox"]');
        expect(labelledByText(trigger)?.replace('*', '')).toBe('Role');
    });
});

describe('SelectField', () => {
    it('wires the label, description and error to the trigger', async () => {
        const container = await render(
            <SelectField
                name="country"
                label="Country"
                value="pl"
                onChange={vi.fn()}
                description="Used for invoicing"
                error="Required"
                options={[
                    { value: 'pl', label: 'Poland' },
                    { value: 'de', label: 'Germany' },
                ]}
            />,
        );

        const trigger = container.querySelector('button[role="combobox"]');
        expect(trigger?.getAttribute('aria-invalid')).toBe('true');
        expect(trigger?.getAttribute('aria-describedby')).toBeTruthy();
        expect(container.querySelector('label')?.textContent).toBe('Country');
    });
});

describe('Tabs', () => {
    it('switches the active panel when a tab is activated', async () => {
        const container = await render(
            <Tabs
                ariaLabel="Settings"
                items={[
                    {
                        value: 'general',
                        label: 'General',
                        content: 'General panel',
                    },
                    {
                        value: 'billing',
                        label: 'Billing',
                        content: 'Billing panel',
                    },
                ]}
            />,
        );

        expect(container.textContent).toContain('General panel');

        const billingTab = Array.from(
            container.querySelectorAll('button[role="tab"]'),
        ).find((tab) => tab.textContent === 'Billing') as HTMLButtonElement;

        await act(async () => {
            billingTab.dispatchEvent(
                new MouseEvent('pointerdown', { bubbles: true }),
            );
            billingTab.dispatchEvent(
                new MouseEvent('mousedown', { bubbles: true }),
            );
            billingTab.click();
        });

        expect(container.textContent).toContain('Billing panel');
    });
});
