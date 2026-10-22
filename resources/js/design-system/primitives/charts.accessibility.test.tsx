import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { BarChart } from './bar-chart';
import { DistributionBar } from './distribution-bar';
import { ItemList } from './item-list';
import { StatTile } from './stat-tile';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string;
        children: ReactNode;
        [key: string]: unknown;
    }) => (
        <a href={href} {...props}>
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
    await act(async () => root.render(node));

    return container;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

const labels = {
    showTable: 'Show table',
    hideTable: 'Hide table',
    category: 'Day',
    total: 'Total',
};

const series = [
    { id: 'sent', label: 'Delivered', tone: 'chart-1' as const },
    {
        id: 'failed',
        label: 'Not delivered',
        tone: 'chart-2' as const,
        hatched: true,
    },
];

const data = [
    {
        key: 'a',
        label: '1.10',
        fullLabel: '1 October',
        values: { sent: 2, failed: 0 },
    },
    {
        key: 'b',
        label: '2.10',
        fullLabel: '2 October',
        values: { sent: 1, failed: 3 },
    },
];

describe('BarChart', () => {
    it('summarises the plot, names both series and keeps every value in a table', async () => {
        const container = await render(
            <BarChart
                title="Messages"
                summary="2 days, 6 messages."
                series={series}
                data={data}
                labels={labels}
            />,
        );
        const plot = container.querySelector('[role="img"]');
        const toggle = container.querySelector<HTMLButtonElement>(
            'button[aria-expanded]',
        );

        expect(plot?.getAttribute('aria-label')).toBe(
            'Messages. 2 days, 6 messages.',
        );
        expect(container.textContent).toContain('Not delivered');
        expect(toggle?.getAttribute('aria-expanded')).toBe('false');

        await act(async () => toggle?.click());
        expect(toggle?.getAttribute('aria-expanded')).toBe('true');
        expect(
            Array.from(container.querySelectorAll('tbody tr')).map(
                (row) => row.textContent,
            ),
        ).toEqual(['1 October202', '2 October134']);
    });

    it('shows the values of a day on keyboard focus with the arrow keys', async () => {
        const container = await render(
            <BarChart
                title="Messages"
                summary="Summary"
                series={series}
                data={data}
                labels={labels}
            />,
        );
        const plot = container.querySelector<HTMLElement>('[role="img"]');

        await act(async () => {
            plot?.dispatchEvent(
                new KeyboardEvent('keydown', {
                    key: 'ArrowLeft',
                    bubbles: true,
                }),
            );
        });

        expect(plot?.textContent).toContain('2 October');
        expect(plot?.textContent).toContain('3');
    });

    it('replaces an all-zero plot with the empty message', async () => {
        const container = await render(
            <BarChart
                title="Messages"
                summary="Nothing yet"
                series={series}
                data={[{ ...data[0], values: { sent: 0, failed: 0 } }]}
                labels={labels}
                empty="No messages yet."
            />,
        );

        expect(container.querySelector('[role="img"]')).toBeNull();
        expect(container.textContent).toContain('No messages yet.');
    });
});

describe('DistributionBar and StatTile', () => {
    it('lists every part with its count, so nothing depends on colour', async () => {
        const container = await render(
            <DistributionBar
                label="Articles"
                emptyLabel="None"
                segments={[
                    { id: 'p', label: 'Published', value: 4, tone: 'chart-1' },
                    { id: 'd', label: 'Drafts', value: 1, tone: 'neutral' },
                ]}
            />,
        );

        expect(container.textContent).toContain('Articles5');
        expect(container.querySelector('li')?.textContent).toBe('Published4');
    });

    it('says the direction of a change in words and links the whole tile', async () => {
        const container = await render(
            <StatTile
                label="Messages"
                value="12"
                delta={{ value: -3, label: 'vs last week' }}
                href="/admin/contact"
                linkLabel="Messages: 12"
            />,
        );
        const link = container.querySelector('a');

        expect(link?.getAttribute('href')).toBe('/admin/contact');
        expect(link?.getAttribute('aria-label')).toBe('Messages: 12');
        expect(link?.textContent).toContain('-3 vs last week');
    });
});

describe('ItemList', () => {
    it('keeps a start slot on every row once any row has one', async () => {
        const container = await render(
            <ItemList
                items={[
                    {
                        id: 'a',
                        lead: <b>10</b>,
                        content: 'Failed',
                        end: 'Review',
                    },
                    { id: 'b', content: 'Optional 2FA', end: 'Review' },
                ]}
            />,
        );
        const rows = container.querySelectorAll('li');

        expect(rows).toHaveLength(2);
        expect(rows[1].children).toHaveLength(3);
        expect(rows[1].textContent).toBe('Optional 2FAReview');
    });
});
