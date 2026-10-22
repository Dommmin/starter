import type { ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vite-plus/test';
import { TagList } from './tag-list';

const items = [
    { id: 'news', label: 'News', href: '/articles?tag=news' },
    {
        id: 'guides',
        label: 'Guides',
        href: '/articles?tag=guides',
        current: true,
    },
];

function render(node: ReactNode): HTMLElement {
    const container = document.createElement('div');
    container.innerHTML = renderToStaticMarkup(node);

    return container;
}

describe('TagList', () => {
    it('renders inline tags as a named list of links', () => {
        const container = render(<TagList label="Tags" items={items} />);
        const list = container.querySelector('ul');

        expect(list?.getAttribute('aria-label')).toBe('Tags');
        expect(container.querySelector('nav')).toBeNull();
        expect(
            Array.from(container.querySelectorAll('a')).map((link) => [
                link.textContent,
                link.getAttribute('href'),
            ]),
        ).toEqual([
            ['News', '/articles?tag=news'],
            ['Guides', '/articles?tag=guides'],
        ]);
    });

    it('renders the filter as a navigation landmark with the current tag', () => {
        const container = render(
            <TagList label="Filter by tag" items={items} variant="filter" />,
        );
        const links = container.querySelectorAll('a');

        expect(container.querySelector('nav')?.getAttribute('aria-label')).toBe(
            'Filter by tag',
        );
        expect(container.querySelector('ul')?.hasAttribute('aria-label')).toBe(
            false,
        );
        expect(links[0].hasAttribute('aria-current')).toBe(false);
        expect(links[1].getAttribute('aria-current')).toBe('page');
    });

    it('renders nothing without tags', () => {
        expect(renderToStaticMarkup(<TagList label="Tags" items={[]} />)).toBe(
            '',
        );
    });
});
