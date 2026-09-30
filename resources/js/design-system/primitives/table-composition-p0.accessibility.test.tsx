import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FilterBar } from './filter-bar';
import { Paginator } from './paginator';
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

describe('Paginator', () => {
    it('disables the edge control and reports the adjacent page', async () => {
        const onPageChange = vi.fn();
        const container = await render(
            <Paginator
                page={1}
                totalPages={3}
                onPageChange={onPageChange}
                previousLabel="Previous page"
                nextLabel="Next page"
                summary="Page 1 of 3"
            />,
        );
        const previous = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Previous page"]',
        );
        const next = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Next page"]',
        );

        expect(container.querySelector('nav')?.getAttribute('aria-label')).toBe(
            'Page 1 of 3',
        );
        expect(previous?.disabled).toBe(true);
        expect(next?.disabled).toBe(false);

        await act(async () => next?.click());

        expect(onPageChange).toHaveBeenCalledWith(2);
    });

    it('uses the 44 px icon button target for both controls', async () => {
        const container = await render(
            <Paginator
                page={2}
                totalPages={3}
                onPageChange={() => {}}
                previousLabel="Previous page"
                nextLabel="Next page"
                summary="Page 2 of 3"
            />,
        );
        const buttons = container.querySelectorAll('nav button');

        expect(buttons).toHaveLength(2);
        buttons.forEach((button) =>
            expect(button.classList.contains('size-11')).toBe(true),
        );
    });
});
