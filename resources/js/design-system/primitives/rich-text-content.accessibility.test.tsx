import { renderToString } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { RichTextContent } from './rich-text-content';

const html =
    '<h2>Offer</h2><p>Read <a href="https://example.test" rel="noopener noreferrer nofollow">more</a> and <code>npm ci</code>.</p><ul><li><p>One</p></li></ul><blockquote><p>Quote</p></blockquote>';

describe('RichTextContent', () => {
    it('renders server-sanitised HTML with semantic structure in SSR output', () => {
        const output = renderToString(<RichTextContent html={html} />);
        const container = document.createElement('div');
        container.innerHTML = output;

        expect(container.querySelector('h2')?.textContent).toBe('Offer');
        expect(container.querySelector('a')?.getAttribute('href')).toBe(
            'https://example.test',
        );
        expect(container.querySelector('ul li')?.textContent).toBe('One');
        expect(container.querySelector('blockquote')?.textContent).toBe(
            'Quote',
        );
        expect(container.querySelector('code')?.textContent).toBe('npm ci');
    });
});
