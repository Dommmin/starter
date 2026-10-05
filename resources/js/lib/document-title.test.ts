import type { Page } from '@inertiajs/core';
import { describe, expect, it } from 'vitest';
import { documentTitle } from './document-title';

const page = { props: { site: { name: 'Acme' } } } as unknown as Page;

describe('documentTitle', () => {
    it('appends the site name to a page title', () => {
        expect(documentTitle('Articles', page)).toBe('Articles - Acme');
    });

    it('does not repeat a site name the title already contains', () => {
        expect(documentTitle('Acme', page)).toBe('Acme');
        expect(documentTitle('Acme — Modern platform', page)).toBe(
            'Acme — Modern platform',
        );
    });

    it('falls back to the site name without a title', () => {
        expect(documentTitle('', page)).toBe('Acme');
    });
});
