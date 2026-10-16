import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ResourceForm } from './resource-form';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

type Values = { name: string; active: boolean };

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

afterEach(() => {
    for (const root of mountedRoots.splice(0)) {
        act(() => root.unmount());
    }
    document.body.innerHTML = '';
});

function form(readOnly: boolean, onSubmit: () => void) {
    return (
        <ResourceForm<Values>
            readOnly={readOnly}
            sections={[
                {
                    id: 'main',
                    title: 'Main',
                    fields: [
                        { type: 'text', name: 'name', label: 'Name' },
                        { type: 'switch', name: 'active', label: 'Active' },
                    ],
                },
            ]}
            values={{ name: 'Ada', active: true }}
            onChange={() => {}}
            onSubmit={onSubmit}
            cancelHref="/admin"
            labels={{
                submit: 'Save',
                cancel: 'Back',
                errorSummaryTitle: 'Fix the fields',
            }}
        />
    );
}

describe('ResourceForm readOnly', () => {
    it('disables every field, hides the submit button and never submits', async () => {
        const onSubmit = vi.fn();
        const container = await render(form(true, onSubmit));

        expect(
            container.querySelector<HTMLInputElement>('input[name="name"]')
                ?.disabled,
        ).toBe(true);
        expect(container.querySelector('button[type="submit"]')).toBeNull();
        expect(container.textContent).toContain('Back');

        await act(async () => {
            container.querySelector('form')!.requestSubmit();
        });
        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('keeps the editable form unchanged by default', async () => {
        const container = await render(form(false, vi.fn()));

        expect(
            container.querySelector<HTMLInputElement>('input[name="name"]')
                ?.disabled,
        ).toBe(false);
        expect(
            container.querySelector('button[type="submit"]')?.textContent,
        ).toBe('Save');
    });
});
