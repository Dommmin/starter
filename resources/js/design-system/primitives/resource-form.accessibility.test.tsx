import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    ResourceForm,
    type ResourceFormProps,
    type ResourceFormSection,
} from './resource-form';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

type Values = {
    name: string;
    bio: string;
    active: boolean;
    price: string;
    launchedOn: string;
};

const sections: ResourceFormSection<Values>[] = [
    {
        id: 'profile',
        title: 'Profile',
        fields: [
            { type: 'text', name: 'name', label: 'Name', required: true },
            { type: 'textarea', name: 'bio', label: 'Bio', hint: 'Short' },
            { type: 'switch', name: 'active', label: 'Active' },
            {
                type: 'number',
                name: 'price',
                label: 'Price',
                step: 0.01,
                min: 0,
            },
            { type: 'date', name: 'launchedOn', label: 'Launched on' },
        ],
    },
];

const mountedRoots: Root[] = [];
let root: Root;

function props(
    overrides: Partial<ResourceFormProps<Values>> = {},
): ResourceFormProps<Values> {
    return {
        sections,
        values: {
            name: 'Ada',
            bio: '',
            active: true,
            price: '9.99',
            launchedOn: '',
        },
        onChange: () => {},
        onSubmit: () => {},
        labels: { submit: 'Save', errorSummaryTitle: 'Fix these fields' },
        ...overrides,
    };
}

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(node);
    });

    return container;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((mounted) => mounted.unmount());
    });
    document.body.replaceChildren();
});

describe('ResourceForm', () => {
    it('renders labelled fields inside a labelled section', async () => {
        const container = await render(<ResourceForm<Values> {...props()} />);

        const section = container.querySelector('section');
        expect(section?.getAttribute('aria-labelledby')).toBeTruthy();
        const name =
            container.querySelector<HTMLInputElement>('input[name="name"]');
        expect(name?.value).toBe('Ada');
        expect(
            container.querySelector(`label[for="${name?.id}"]`)?.textContent,
        ).toContain('Name');
    });

    it('reports typed changes by field name', async () => {
        const onChange = vi.fn();
        const container = await render(
            <ResourceForm<Values> {...props({ onChange })} />,
        );
        const name =
            container.querySelector<HTMLInputElement>('input[name="name"]')!;

        await act(async () => {
            Object.getOwnPropertyDescriptor(
                HTMLInputElement.prototype,
                'value',
            )!.set!.call(name, 'Grace');
            name.dispatchEvent(new Event('input', { bubbles: true }));
        });

        expect(onChange).toHaveBeenCalledWith('name', 'Grace');
    });

    it('shows server errors at the field and focuses the first invalid field', async () => {
        const container = await render(<ResourceForm<Values> {...props()} />);

        await act(async () => {
            root.render(
                <ResourceForm<Values>
                    {...props({
                        errors: {
                            bio: 'Bio is too long.',
                            name: 'Name is required.',
                        },
                    })}
                />,
            );
        });

        const name =
            container.querySelector<HTMLInputElement>('input[name="name"]')!;
        expect(name.getAttribute('aria-invalid')).toBe('true');
        const describedBy = name.getAttribute('aria-describedby') ?? '';
        expect(
            describedBy
                .split(' ')
                .map((id) => document.getElementById(id)?.textContent)
                .join(' '),
        ).toContain('Name is required.');
        expect(
            container.querySelector('[role="alert"]')?.textContent,
        ).toContain('Fix these fields');
        expect(document.activeElement).toBe(name);
    });

    it('does not submit twice while the first submit is pending', async () => {
        const onSubmit = vi.fn();
        const container = await render(
            <ResourceForm<Values> {...props({ onSubmit })} />,
        );
        const form = container.querySelector('form')!;

        await act(async () => {
            form.requestSubmit();
            form.requestSubmit();
        });
        expect(onSubmit).toHaveBeenCalledTimes(1);

        await act(async () => {
            root.render(
                <ResourceForm<Values>
                    {...props({ onSubmit, isPending: true })}
                />,
            );
        });
        await act(async () => {
            form.requestSubmit();
        });
        expect(onSubmit).toHaveBeenCalledTimes(1);
        expect(form.getAttribute('aria-busy')).toBe('true');
        expect(
            container.querySelector<HTMLInputElement>('input[name="name"]')
                ?.disabled,
        ).toBe(true);

        await act(async () => {
            root.render(
                <ResourceForm<Values>
                    {...props({ onSubmit, isPending: false })}
                />,
            );
        });
        await act(async () => {
            form.requestSubmit();
        });
        expect(onSubmit).toHaveBeenCalledTimes(2);
    });

    it('renders number and date fields as native inputs with string values', async () => {
        const onChange = vi.fn();
        const container = await render(
            <ResourceForm<Values>
                {...props({
                    onChange,
                    errors: { launchedOn: 'Enter a valid date.' },
                })}
            />,
        );

        const price = container.querySelector<HTMLInputElement>(
            'input[name="price"]',
        )!;
        expect(price.type).toBe('number');
        expect(price.value).toBe('9.99');
        expect(price.step).toBe('0.01');
        expect(price.min).toBe('0');
        expect(price.inputMode).toBe('decimal');

        const launchedOn = container.querySelector<HTMLInputElement>(
            'input[name="launchedOn"]',
        )!;
        expect(launchedOn.type).toBe('date');
        expect(launchedOn.value).toBe('');
        expect(launchedOn.getAttribute('aria-invalid')).toBe('true');
        expect(
            container.querySelector(`label[for="${launchedOn.id}"]`)
                ?.textContent,
        ).toBe('Launched on');

        await act(async () => {
            Object.getOwnPropertyDescriptor(
                HTMLInputElement.prototype,
                'value',
            )!.set!.call(price, '12.50');
            price.dispatchEvent(new Event('input', { bubbles: true }));
        });

        expect(onChange).toHaveBeenCalledWith('price', '12.50');
    });
});
