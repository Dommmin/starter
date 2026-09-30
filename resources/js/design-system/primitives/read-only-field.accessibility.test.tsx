import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DateField } from './date-field';
import { NumberField } from './number-field';
import { TextareaField } from './textarea-field';
import { TextField } from './text-field';

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

const cases = [
    {
        name: 'TextField',
        node: (onChange: (value: string) => void) => (
            <TextField
                name="slug"
                label="Slug"
                value="about-us"
                onChange={onChange}
                required
                readOnly
            />
        ),
        value: 'about-us',
    },
    {
        name: 'TextareaField',
        node: (onChange: (value: string) => void) => (
            <TextareaField
                name="error"
                label="Last error"
                value="SMTP timeout"
                onChange={onChange}
                required
                readOnly
            />
        ),
        value: 'SMTP timeout',
    },
    {
        name: 'NumberField',
        node: (onChange: (value: string) => void) => (
            <NumberField
                name="price"
                label="Price"
                value="129.99"
                onChange={onChange}
                required
                readOnly
            />
        ),
        value: '129.99',
    },
    {
        name: 'DateField',
        node: (onChange: (value: string) => void) => (
            <DateField
                name="published"
                label="Published"
                value="2026-10-15"
                onChange={onChange}
                required
                readOnly
            />
        ),
        value: '2026-10-15',
    },
] as const;

describe.each(cases)('$name readOnly', ({ node, value }) => {
    it('uses native readonly (not disabled) and keeps the value in the form', async () => {
        const onChange = vi.fn();
        const container = await render(<form>{node(onChange)}</form>);
        const control = container.querySelector<
            HTMLInputElement | HTMLTextAreaElement
        >('input, textarea')!;

        expect(control.readOnly).toBe(true);
        expect(control.disabled).toBe(false);
        expect(control.getAttribute('aria-readonly')).toBeNull();
        expect(control.value).toBe(value);

        const data = new FormData(container.querySelector('form')!);
        expect(data.get(control.name)).toBe(value);
    });

    it('stays in the keyboard order with its value intact', async () => {
        const onChange = vi.fn();
        const container = await render(node(onChange));
        const control = container.querySelector<
            HTMLInputElement | HTMLTextAreaElement
        >('input, textarea')!;

        control.focus();
        expect(document.activeElement).toBe(control);
        expect(control.tabIndex).toBe(0);

        expect(onChange).not.toHaveBeenCalled();
        expect(control.value).toBe(value);
    });

    it('drops the required marker and constraint while read-only', async () => {
        const container = await render(node(vi.fn()));
        const control = container.querySelector<
            HTMLInputElement | HTMLTextAreaElement
        >('input, textarea')!;

        expect(control.required).toBe(false);
        expect(container.querySelector('label')?.textContent).not.toContain(
            '*',
        );
    });
});

describe('editable fields', () => {
    it('keep the required marker and are not read-only by default', async () => {
        const container = await render(
            <TextField
                name="title"
                label="Title"
                value=""
                onChange={vi.fn()}
                required
            />,
        );
        const input = container.querySelector('input')!;

        expect(input.readOnly).toBe(false);
        expect(input.required).toBe(true);
        expect(container.querySelector('label')?.textContent).toContain('*');
    });

    it('require onChange unless read-only', () => {
        // @ts-expect-error An editable field must handle changes.
        void (<TextField name="title" label="Title" value="" />);
        void (<TextField name="slug" label="Slug" value="a" readOnly />);
    });
});
