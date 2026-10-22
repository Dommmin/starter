import type { ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vite-plus/test';
import { RelatedRecords } from '@/components/admin/related-records';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: { href: string; children: ReactNode }) => (
        <a href={href}>{children}</a>
    ),
}));

const labels = {
    empty: 'Nothing yet.',
    shown: (shown: number, total: number) => `Showing ${shown} of ${total}.`,
    showAll: 'Open the full list',
};

describe('RelatedRecords', () => {
    it('links each record and says how many more exist', () => {
        const html = renderToStaticMarkup(
            <RelatedRecords
                labels={labels}
                group={{
                    relation: 'products',
                    total: 3,
                    listUrl: '/admin/products',
                    items: [
                        { id: 2, label: 'Lamp', url: '/admin/products/2/edit' },
                        { id: 1, label: '', url: '/admin/products/1/edit' },
                    ],
                }}
            />,
        );

        expect(html).toContain('href="/admin/products/2/edit">Lamp</a>');
        expect(html).toContain('href="/admin/products/1/edit">#1</a>');
        expect(html).toContain('Showing 2 of 3.');
        expect(html).toContain('href="/admin/products">Open the full list</a>');
    });

    it('shows the empty state without a count', () => {
        const html = renderToStaticMarkup(
            <RelatedRecords
                labels={labels}
                group={{
                    relation: 'products',
                    total: 0,
                    listUrl: '/admin/products',
                    items: [],
                }}
            />,
        );

        expect(html).toContain('Nothing yet.');
        expect(html).not.toContain('Showing');
    });
});
