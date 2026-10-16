import { describe, expect, it } from 'vitest';
import { isSameOrChildPath } from './path-match';

describe('isSameOrChildPath', () => {
    it('matches the path and its sub-pages only on segment boundaries', () => {
        expect(isSameOrChildPath('/admin/contact', '/admin/contact')).toBe(
            true,
        );
        expect(isSameOrChildPath('/admin/contact/7', '/admin/contact')).toBe(
            true,
        );
        expect(
            isSameOrChildPath('/admin/contact-forms', '/admin/contact'),
        ).toBe(false);
        expect(isSameOrChildPath('/admin', '/admin/contact')).toBe(false);
    });
});
