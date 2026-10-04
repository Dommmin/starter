import type { ReactNode } from 'react';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CenteredLayout } from './centered-layout';
import { SectionNav } from './section-nav';
import { TextField } from './text-field';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string;
        children: ReactNode;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const roots: Root[] = [];

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    roots.push(root);
    await act(async () => root.render(node));

    return container;
}

afterEach(async () => {
    await act(async () => roots.splice(0).forEach((root) => root.unmount()));
    document.body.replaceChildren();
});

describe('SectionNav', () => {
    it('is a named landmark and marks only the current page', async () => {
        const container = await render(
            <SectionNav
                ariaLabel="Account settings"
                items={[
                    { id: 'a', label: 'Profile', href: '/a', current: false },
                    { id: 'b', label: 'Security', href: '/b', current: true },
                ]}
            />,
        );

        expect(container.querySelector('nav')?.getAttribute('aria-label')).toBe(
            'Account settings',
        );
        const current = container.querySelectorAll('[aria-current="page"]');
        expect(current).toHaveLength(1);
        expect(current[0].textContent).toBe('Security');
    });
});

describe('CenteredLayout', () => {
    it('renders the main landmark that the skip link targets', async () => {
        const container = await render(<CenteredLayout>Body</CenteredLayout>);

        expect(container.querySelector('main#main-content')?.textContent).toBe(
            'Body',
        );
    });
});

describe('TextField autoFocus', () => {
    it('moves focus into the field on mount only when asked', async () => {
        const container = await render(
            <>
                <TextField name="a" label="A" value="" onChange={() => {}} />
                <TextField
                    name="b"
                    label="B"
                    value=""
                    onChange={() => {}}
                    autoFocus
                />
            </>,
        );

        expect(document.activeElement).toBe(
            container.querySelector('input[name="b"]'),
        );
    });
});
