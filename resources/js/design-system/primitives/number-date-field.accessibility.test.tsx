import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DateField } from './date-field';
import { NumberField } from './number-field';

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

async function typeInto(input: HTMLInputElement, value: string): Promise<void> {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value',
        )!.set!.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

function describedText(input: HTMLInputElement): string {
    return (input.getAttribute('aria-describedby') ?? '')
        .split(' ')
        .map((id) => document.getElementById(id)?.textContent)
        .join(' ');
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('NumberField', () => {
    it('renders a labelled native number input with hint and error', async () => {
        const container = await render(
            <NumberField
                id="position"
                name="position"
                label="Position"
                value="3"
                onChange={vi.fn()}
                description="Lower comes first"
                error="Must be a whole number."
                min={0}
                max={100}
                step={1}
                required
            />,
        );

        const input = container.querySelector('input')!;
        expect(input.type).toBe('number');
        expect(input.id).toBe('position');
        expect(input.value).toBe('3');
        expect(input.inputMode).toBe('numeric');
        expect(input.min).toBe('0');
        expect(input.max).toBe('100');
        expect(input.step).toBe('1');
        expect(input.required).toBe(true);
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(
            container.querySelector('label[for="position"]')?.textContent,
        ).toContain('Position');
        expect(describedText(input)).toContain('Lower comes first');
        expect(describedText(input)).toContain('Must be a whole number.');
        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'Must be a whole number.',
        );
    });

    it('uses the decimal keyboard for fractional steps and reports raw strings', async () => {
        const onChange = vi.fn();
        const container = await render(
            <NumberField
                name="price"
                label="Price"
                value=""
                onChange={onChange}
                step={0.01}
            />,
        );

        const input = container.querySelector('input')!;
        expect(input.inputMode).toBe('decimal');
        expect(input.hasAttribute('aria-invalid')).toBe(false);
        expect(input.hasAttribute('aria-describedby')).toBe(false);

        await typeInto(input, '12.5');
        expect(onChange).toHaveBeenCalledWith('12.5');
    });
});

describe('DateField', () => {
    it('renders a labelled native date input and reports YYYY-MM-DD', async () => {
        const onChange = vi.fn();
        const container = await render(
            <DateField
                name="launched_on"
                label="Launched on"
                value="2026-01-15"
                onChange={onChange}
                min="2020-01-01"
                max="2030-12-31"
                error="Enter a valid date."
            />,
        );

        const input = container.querySelector('input')!;
        expect(input.type).toBe('date');
        expect(input.value).toBe('2026-01-15');
        expect(input.min).toBe('2020-01-01');
        expect(input.max).toBe('2030-12-31');
        expect(
            container.querySelector(`label[for="${input.id}"]`)?.textContent,
        ).toBe('Launched on');
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(describedText(input)).toBe('Enter a valid date.');

        await typeInto(input, '2026-02-01');
        expect(onChange).toHaveBeenCalledWith('2026-02-01');
    });

    it('can be disabled', async () => {
        const container = await render(
            <DateField
                name="due_on"
                label="Due on"
                value=""
                onChange={vi.fn()}
                disabled
            />,
        );

        expect(container.querySelector('input')?.disabled).toBe(true);
    });
});
