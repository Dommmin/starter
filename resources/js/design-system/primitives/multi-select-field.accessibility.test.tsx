import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    MultiSelectField,
    type MultiSelectFieldLabels,
} from './multi-select-field';

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

const cities = [
    { value: 'krk', label: 'Kraków' },
    { value: 'waw', label: 'Warszawa' },
    { value: 'gdn', label: 'Gdańsk' },
    { value: 'poz', label: 'Poznań', disabled: true },
];

const labels: MultiSelectFieldLabels = {
    noResults: 'No matching cities',
    remove: (label) => `Remove ${label}`,
    selected: (count) => `${count} selected`,
};

function Controlled({
    initial = [],
    onChange,
    max,
}: {
    initial?: string[];
    onChange?: (value: string[]) => void;
    max?: number;
}) {
    const [value, setValue] = useState(initial);

    return (
        <MultiSelectField
            name="cities"
            label="Cities"
            value={value}
            onChange={(next) => {
                setValue(next);
                onChange?.(next);
            }}
            options={cities}
            labels={labels}
            placeholder="Search cities"
            description="Pick the offices"
            max={max}
        />
    );
}

function combobox(): HTMLInputElement {
    const input = document.querySelector<HTMLInputElement>(
        'input[role="combobox"]',
    );

    if (!input) {
        throw new Error('Combobox not rendered.');
    }

    return input;
}

async function press(key: string): Promise<void> {
    await act(async () => {
        combobox().dispatchEvent(
            new KeyboardEvent('keydown', { key, bubbles: true }),
        );
    });
}

async function type(text: string): Promise<void> {
    const input = combobox();

    await act(async () => {
        Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value',
        )!.set!.call(input, text);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

function optionTexts(): string[] {
    return [...document.querySelectorAll('[role="option"]')].map(
        (option) => option.textContent ?? '',
    );
}

describe('MultiSelectField', () => {
    it('is a labelled combobox that opens a multi-selectable listbox from the keyboard', async () => {
        await render(<Controlled />);
        const input = combobox();

        expect(
            document.querySelector(`label[for="${input.id}"]`)?.textContent,
        ).toBe('Cities');
        expect(input.getAttribute('aria-expanded')).toBe('false');
        expect(input.getAttribute('aria-autocomplete')).toBe('list');
        expect(
            document.getElementById(
                input.getAttribute('aria-describedby') ?? '',
            )?.textContent,
        ).toBe('Pick the offices');

        await press('ArrowDown');

        const listbox = document.getElementById(
            input.getAttribute('aria-controls') ?? '',
        );
        expect(input.getAttribute('aria-expanded')).toBe('true');
        expect(listbox?.getAttribute('role')).toBe('listbox');
        expect(listbox?.getAttribute('aria-multiselectable')).toBe('true');
        expect(optionTexts()).toEqual([
            'Kraków',
            'Warszawa',
            'Gdańsk',
            'Poznań',
        ]);
        expect(
            document.getElementById(
                input.getAttribute('aria-activedescendant') ?? '',
            )?.textContent,
        ).toBe('Kraków');
    });

    it('toggles the active option with Enter, announces the count and closes on Escape', async () => {
        const onChange = vi.fn();
        await render(<Controlled onChange={onChange} />);

        await press('ArrowDown');
        await press('ArrowDown');
        await press('Enter');

        expect(onChange).toHaveBeenLastCalledWith(['waw']);
        expect(
            document
                .querySelector('[role="option"][aria-selected="true"]')
                ?.textContent?.trim(),
        ).toBe('Warszawa');
        expect(document.querySelector('[role="status"]')?.textContent).toBe(
            '1 selected',
        );
        expect(
            document.querySelector<HTMLInputElement>(
                'input[type="hidden"][name="cities[]"]',
            )?.value,
        ).toBe('waw');

        await press('Escape');
        expect(combobox().getAttribute('aria-expanded')).toBe('false');
    });

    it('filters ignoring diacritics and reports when nothing matches', async () => {
        await render(<Controlled />);

        await type('krakow');
        expect(optionTexts()).toEqual(['Kraków']);

        await type('lublin');
        expect(optionTexts()).toEqual([]);
        expect(document.body.textContent).toContain('No matching cities');
    });

    it('removes chips with their labelled button and with Backspace', async () => {
        const onChange = vi.fn();
        await render(
            <Controlled initial={['krk', 'gdn']} onChange={onChange} />,
        );

        const removeKrakow = document.querySelector<HTMLButtonElement>(
            'button[aria-label="Remove Kraków"]',
        );
        expect(removeKrakow).not.toBeNull();

        await act(async () => {
            removeKrakow?.click();
        });
        expect(onChange).toHaveBeenLastCalledWith(['gdn']);
        expect(document.activeElement).toBe(combobox());

        await press('Backspace');
        expect(onChange).toHaveBeenLastCalledWith([]);
    });

    it('keeps disabled options and options beyond max out of the selection', async () => {
        const onChange = vi.fn();
        await render(
            <Controlled initial={['krk']} onChange={onChange} max={1} />,
        );

        await press('ArrowDown');

        const unavailable = [
            ...document.querySelectorAll('[role="option"]'),
        ].map((option) => option.getAttribute('aria-disabled'));
        expect(unavailable).toEqual([null, 'true', 'true', 'true']);

        await press('ArrowDown');
        await press('Enter');
        expect(onChange).not.toHaveBeenCalled();
    });
});
