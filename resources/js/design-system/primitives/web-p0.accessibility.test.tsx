import { Rocket } from 'lucide-react';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { CTA } from './cta';
import { FeatureGrid } from './feature-grid';
import { Hero } from './hero';
import { StatList } from './stat-list';

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
        expect(container.querySelector('h2')?.textContent).toBe('Why us');
        expect(
            Array.from(container.querySelectorAll('h3')).map(
                (heading) => heading.textContent,
            ),
        ).toEqual(['Fast', 'Safe']);
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

describe('StatList', () => {
    it('pairs each value with its label in a description list without headings', async () => {
        const container = await render(
            <StatList
                items={[
                    { id: 'projects', value: '300+', label: 'Projects' },
                    { id: 'clients', value: '12', label: 'Countries' },
                ]}
            />,
        );

        const terms = Array.from(container.querySelectorAll('dl dt')).map(
            (term) => term.textContent,
        );
        const values = Array.from(container.querySelectorAll('dl dd')).map(
            (value) => value.textContent,
        );

        expect(terms).toEqual(['Projects', 'Countries']);
        expect(values).toEqual(['300+', '12']);
        expect(container.querySelector('h1, h2, h3, h4, h5, h6')).toBeNull();
    });

    it('renders nothing without items', async () => {
        const container = await render(<StatList items={[]} />);

        expect(container.querySelector('dl')).toBeNull();
    });
});
