import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import {
    ResourceForm,
    type ResourceFormRepeaterItem,
    type ResourceFormSection,
} from './resource-form';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
    router: { on: () => () => {} },
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

type Values = {
    title: string;
    items: ResourceFormRepeaterItem[];
};

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
                repeater: {
                    remove: 'Remove :label',
                    added: ':label added',
                    removed: ':label removed',
                    limit: ':count of :max items',
                },
            },
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const sections: ResourceFormSection<Values>[] = [
    {
        id: 'content',
        title: 'Content',
        fields: [
            { type: 'text', name: 'title', label: 'Heading' },
            {
                type: 'repeater',
                name: 'items',
                label: 'Features',
                maxItems: 3,
                newItem: () => ({ title: '', description: '' }),
                labels: {
                    add: 'Add feature',
                    item: (position) => `Feature ${position}`,
                },
                itemFields: [
                    {
                        type: 'text',
                        name: 'title',
                        label: 'Title',
                        required: true,
                    },
                    {
                        type: 'textarea',
                        name: 'description',
                        label: 'Description',
                    },
                ],
            },
        ],
    },
];

const mountedRoots: Root[] = [];
let latest: Values = { title: '', items: [] };

function Form({
    initial,
    errors = {},
}: {
    initial: ResourceFormRepeaterItem[];
    errors?: Partial<Record<string, string>>;
}) {
    const [values, setValues] = useState<Values>({
        title: '',
        items: initial,
    });
    latest = values;

    return (
        <ResourceForm<Values>
            sections={sections}
            values={values}
            errors={errors}
            onChange={(name, value) =>
                setValues((current) => ({ ...current, [name]: value }))
            }
            onSubmit={() => {}}
            labels={{ submit: 'Save', errorSummaryTitle: 'Fix these fields' }}
        />
    );
}

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

function button(container: HTMLElement, name: string): HTMLButtonElement {
    const match = Array.from(container.querySelectorAll('button')).find(
        (candidate) =>
            (candidate.getAttribute('aria-label') ?? candidate.textContent) ===
            name,
    );
    if (!match) {
        throw new Error(`No button named "${name}"`);
    }

    return match;
}

async function click(target: HTMLElement) {
    await act(async () => {
        target.click();
    });
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('ResourceForm repeater field', () => {
    it('renders every item as a named group with labelled controls', async () => {
        const container = await render(
            <Form initial={[{ title: 'Fast', description: 'Very' }]} />,
        );

        const list = container.querySelector('fieldset > ol');
        expect(list?.children).toHaveLength(1);
        const group = list?.querySelector('fieldset');
        expect(group?.querySelector('legend')?.textContent).toBe('Feature 1');

        for (const control of Array.from(
            group?.querySelectorAll('input, textarea') ?? [],
        )) {
            const label = container.querySelector(`label[for="${control.id}"]`);
            expect(label?.textContent).toBeTruthy();
        }
        expect(button(container, 'Move Feature 1 up').disabled).toBe(true);
        expect(button(container, 'Move Feature 1 down').disabled).toBe(true);
        expect(button(container, 'Remove Feature 1').disabled).toBe(false);
    });

    it('adds items up to the limit, focuses the new item and announces it', async () => {
        const container = await render(<Form initial={[]} />);

        await click(button(container, 'Add feature'));

        expect(latest.items).toEqual([{ title: '', description: '' }]);
        expect(document.activeElement?.getAttribute('name')).toBe(
            'items[0][title]',
        );
        expect(
            container.querySelector('[aria-live="polite"]')?.textContent,
        ).toBe('Feature 1 added');

        await click(button(container, 'Add feature'));
        await click(button(container, 'Add feature'));

        expect(latest.items).toHaveLength(3);
        expect(button(container, 'Add feature').disabled).toBe(true);
        expect(container.textContent).toContain('3 of 3 items');
    });

    it('moves an item and keeps focus on the moved item', async () => {
        const container = await render(
            <Form
                initial={[
                    { title: 'First', description: '' },
                    { title: 'Second', description: '' },
                ]}
            />,
        );

        await click(button(container, 'Move Feature 1 down'));

        expect(latest.items.map((item) => item.title)).toEqual([
            'Second',
            'First',
        ]);
        expect(document.activeElement).toBe(
            button(container, 'Move Feature 2 up'),
        );
        expect(
            container.querySelector('[aria-live="polite"]')?.textContent,
        ).toBe('Feature 1 moved to position 2 of 2');
    });

    it('removes an item and moves focus to the next one', async () => {
        const container = await render(
            <Form
                initial={[
                    { title: 'First', description: '' },
                    { title: 'Second', description: '' },
                ]}
            />,
        );

        await click(button(container, 'Remove Feature 1'));

        expect(latest.items).toEqual([{ title: 'Second', description: '' }]);
        expect(document.activeElement?.getAttribute('name')).toBe(
            'items[0][title]',
        );
    });

    it('shows item errors next to the field and in the error summary', async () => {
        const container = await render(
            <Form
                initial={[
                    { title: 'First', description: '' },
                    { title: '', description: '' },
                ]}
                errors={{
                    'items.1.title': 'The title is required.',
                    items: 'At most 3 items.',
                }}
            />,
        );

        const input = container.querySelector<HTMLInputElement>(
            'input[name="items[1][title]"]',
        );
        expect(input?.getAttribute('aria-invalid')).toBe('true');
        const describedBy = input?.getAttribute('aria-describedby') ?? '';
        expect(
            describedBy
                .split(' ')
                .map((id) => document.getElementById(id)?.textContent)
                .join(' '),
        ).toContain('The title is required.');

        const summaryLinks = Array.from(
            container.querySelectorAll<HTMLAnchorElement>('a[href^="#"]'),
        );
        expect(
            summaryLinks.some(
                (link) =>
                    link.getAttribute('href') === `#${input?.id}` &&
                    link.textContent?.includes('The title is required.'),
            ),
        ).toBe(true);
        expect(container.textContent).toContain('At most 3 items.');
    });
});
