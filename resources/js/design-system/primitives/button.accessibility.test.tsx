import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Button } from './button';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        className,
    }: {
        children: ReactNode;
        href: string;
        className?: string;
    }) => (
        <a data-inertia-link="" href={href} className={className}>
            {children}
        </a>
    ),
}));

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

describe('Button href', () => {
    it('renders an in-page anchor as a plain link without an Inertia visit', async () => {
        const container = await render(
            <Button href="#contact" variant="outline">
                Contact
            </Button>,
        );

        const link = container.querySelector('a');
        expect(link?.getAttribute('href')).toBe('#contact');
        expect(link?.hasAttribute('data-inertia-link')).toBe(false);
        expect(link?.hasAttribute('download')).toBe(false);
        expect(link?.className).toContain('border-border-subtle');
    });

    it('keeps an Inertia link for paths with the same visual variant', async () => {
        const container = await render(
            <Button href="/articles" variant="outline">
                Articles
            </Button>,
        );

        const link = container.querySelector('a');
        expect(link?.getAttribute('href')).toBe('/articles');
        expect(link?.hasAttribute('data-inertia-link')).toBe(true);
        expect(link?.className).toContain('border-border-subtle');
    });
});
