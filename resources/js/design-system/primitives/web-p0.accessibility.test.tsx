import { Rocket } from 'lucide-react';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { CTA } from './cta';
import { FeatureGrid } from './feature-grid';
import { Hero } from './hero';

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

describe('Hero', () => {
    it('renders the heading as an h1 with the eyebrow and actions', async () => {
        const container = await render(
            <Hero
                eyebrow="New"
                title="Plan your next trip"
                description="Everything in one place"
                actions={<button type="button">Get started</button>}
            />,
        );

        expect(container.querySelector('h1')?.textContent).toBe(
            'Plan your next trip',
        );
        expect(container.textContent).toContain('New');
        expect(container.querySelector('button')?.textContent).toBe(
            'Get started',
        );
    });
});

describe('FeatureGrid', () => {
    it('renders one entry per item', async () => {
        const container = await render(
            <FeatureGrid
                title="Why us"
                items={[
                    {
                        id: 'fast',
                        icon: Rocket,
                        title: 'Fast',
                        description: 'Ships quickly',
                    },
                    {
                        id: 'safe',
                        title: 'Safe',
                        description: 'Reviewed changes',
                    },
                ]}
            />,
        );

        expect(container.textContent).toContain('Fast');
        expect(container.textContent).toContain('Safe');
        expect(container.querySelectorAll('svg')).toHaveLength(1);
    });
});

describe('CTA', () => {
    it('renders the title, description and actions', async () => {
        const container = await render(
            <CTA
                title="Ready to start?"
                description="Join today"
                actions={<button type="button">Sign up</button>}
            />,
        );

        expect(container.querySelector('h2')?.textContent).toBe(
            'Ready to start?',
        );
        expect(container.textContent).toContain('Join today');
        expect(container.querySelector('button')?.textContent).toBe('Sign up');
    });
});
