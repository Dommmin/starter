import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { TypographyPreview } from './typography-preview';

describe('TypographyPreview', () => {
    it('always sets the preset, so default overrides the page preset', () => {
        expect(
            renderToStaticMarkup(
                <TypographyPreview preset="classic">Heading</TypographyPreview>,
            ),
        ).toBe('<div data-typography="classic">Heading</div>');
        expect(
            renderToStaticMarkup(
                <TypographyPreview preset="default">Heading</TypographyPreview>,
            ),
        ).toBe('<div data-typography="default">Heading</div>');
    });
});
