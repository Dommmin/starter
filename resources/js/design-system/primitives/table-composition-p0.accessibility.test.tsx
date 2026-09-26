import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FilterBar } from './filter-bar';
import { SearchInput } from './search-input';

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

describe('SearchInput', () => {
    it('debounces onChange while typing and clears immediately', async () => {
        vi.useFakeTimers();
        const onChange = vi.fn();
        const container = await render(
            <SearchInput
                name="q"
                label="Search users"
                value=""
                onChange={onChange}
                clearLabel="Clear search"
                debounceMs={300}
            />,
        );

        const input = container.querySelector<HTMLInputElement>('input')!;
        const setNativeValue = (element: HTMLInputElement, next: string) => {
            Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype,
                'value',
            )!.set!.call(element, next);
        };

        await act(async () => {
            setNativeValue(input, 'ada');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        expect(onChange).not.toHaveBeenCalled();

        await act(async () => {
            vi.advanceTimersByTime(300);
        });
        expect(onChange).toHaveBeenCalledWith('ada');

        const clearButton = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Clear search"]',
        );
        await act(async () => {
            clearButton?.click();
        });
        expect(onChange).toHaveBeenLastCalledWith('');

        vi.useRealTimers();
    });
});

describe('FilterBar', () => {
    it('renders filter controls alongside trailing actions', async () => {
        const container = await render(
            <FilterBar actions={<button type="button">Clear filters</button>}>
                <span>Status filter</span>
            </FilterBar>,
        );

        expect(container.textContent).toContain('Status filter');
        expect(container.textContent).toContain('Clear filters');
    });
});
