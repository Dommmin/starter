import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import {
    OrderableList,
    type OrderableListItem,
    type OrderableListProps,
} from './orderable-list';

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

const initialPage = {
    props: {
        i18n: {
            locale: 'en',
            fallback: 'en',
            dir: 'ltr',
            messages: {
                orderable: {
                    moveUp: 'Move :label up',
                    moveDown: 'Move :label down',
                    moved: ':label moved to position :position of :total',
                },
            },
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
        );
    });

    return container;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

const initialItems: OrderableListItem[] = [
    { id: 'hero', label: 'Hero', meta: 'Banner' },
    { id: 'features', label: 'Features' },
    { id: 'contact', label: 'Contact' },
];

function ControlledList({
    onReorder,
}: {
    onReorder: OrderableListProps['onReorder'];
}) {
    const [items, setItems] = useState(initialItems);

    return (
        <OrderableList
            label="Sections"
            items={items}
            onReorder={(ids, move) => {
                onReorder(ids, move);
                setItems(
                    ids.map((id) => items.find((item) => item.id === id)!),
                );
            }}
        />
    );
}

function labels(container: HTMLElement): string[] {
    return Array.from(
        container.querySelectorAll(
            'ol > li > div:first-child > span:first-child',
        ),
    ).map((node) => node.textContent ?? '');
}

function button(container: HTMLElement, name: string): HTMLButtonElement {
    const found = container.querySelector<HTMLButtonElement>(
        `button[aria-label="${name}"]`,
    );
    expect(found).not.toBeNull();

    return found!;
}

describe('OrderableList', () => {
    it('disables moving past either end of the list', async () => {
        const container = await render(
            <OrderableList
                label="Sections"
                items={initialItems}
                onReorder={vi.fn()}
            />,
        );

        expect(container.querySelector('ol')?.getAttribute('aria-label')).toBe(
            'Sections',
        );
        const firstUp = button(container, 'Move Hero up');
        const firstDown = button(container, 'Move Hero down');
        const lastUp = button(container, 'Move Contact up');
        const lastDown = button(container, 'Move Contact down');

        expect(firstUp.disabled).toBe(true);
        expect(firstDown.disabled).toBe(false);
        expect(lastUp.disabled).toBe(false);
        expect(lastDown.disabled).toBe(true);
        expect(container.textContent).toContain('Banner');
    });

    it('moves an item, reports the new order, announces it and keeps focus on the moved item', async () => {
        const onReorder = vi.fn();
        const container = await render(
            <ControlledList onReorder={onReorder} />,
        );

        const featuresUp = button(container, 'Move Features up');
        featuresUp.focus();

        await act(async () => {
            featuresUp.click();
        });

        expect(onReorder).toHaveBeenCalledWith(
            ['features', 'hero', 'contact'],
            { id: 'features', direction: 'up' },
        );
        expect(labels(container)).toEqual(['Features', 'Hero', 'Contact']);

        const live = container.querySelector('[aria-live="polite"]');
        expect(live?.textContent).toBe('Features moved to position 1 of 3');

        // The moved item is now first: its "up" button is disabled, so focus
        // falls back to its "down" button instead of being lost.
        expect(button(container, 'Move Features up').disabled).toBe(true);
        expect(document.activeElement).toBe(
            button(container, 'Move Features down'),
        );
    });

    it('does not reorder when the whole list is disabled', async () => {
        const onReorder = vi.fn();
        const container = await render(
            <OrderableList
                label="Sections"
                items={initialItems}
                onReorder={onReorder}
                disabled
            />,
        );

        const buttons = Array.from(container.querySelectorAll('button'));
        expect(buttons.every((control) => control.disabled)).toBe(true);

        await act(async () => {
            buttons[1].click();
        });

        expect(onReorder).not.toHaveBeenCalled();
        expect(button(container, 'Move Hero down').disabled).toBe(true);
    });

    it('keeps a closed styling API', () => {
        const props: OrderableListProps = {
            label: 'Sections',
            items: [],
            onReorder: () => {},
            // @ts-expect-error ADR-019: screens cannot restyle primitives.
            className: 'mt-4',
        };

        expect(props.label).toBe('Sections');
    });
});
