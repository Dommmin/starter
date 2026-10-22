import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { AppearancePreview } from './appearance-preview';

describe('AppearancePreview', () => {
    it('scopes both presets and hides the inert sample behind its label', () => {
        const markup = renderToStaticMarkup(
            <AppearancePreview
                accent="blue"
                typography="classic"
                label="Office skin"
            >
                <button type="button">Write to us</button>
            </AppearancePreview>,
        );

        expect(markup).toContain('data-appearance-preview=""');
        expect(markup).toContain('data-accent="blue"');
        expect(markup).toContain('data-typography="classic"');
        expect(markup).toContain('role="img"');
        expect(markup).toContain('aria-label="Office skin"');
        expect(markup).toContain('inert=""');
    });

    it('always sets the default presets, so they override the page presets', () => {
        const markup = renderToStaticMarkup(
            <AppearancePreview
                accent="default"
                typography="default"
                label="Studio"
            >
                Sample
            </AppearancePreview>,
        );

        expect(markup).toContain('data-accent="default"');
        expect(markup).toContain('data-typography="default"');
    });
});
