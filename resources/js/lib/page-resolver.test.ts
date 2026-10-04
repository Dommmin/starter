import { describe, expect, it } from 'vitest';
import { surfaceFor } from './page-resolver';

const pageNames = Object.keys(
    import.meta.glob(['../pages/**/*.tsx', '!../pages/**/*.test.tsx']),
).map((path) => path.slice('../pages/'.length, -'.tsx'.length));

describe('page layout groups', () => {
    it('assigns every page to a known layout group', () => {
        expect(pageNames.length).toBeGreaterThan(0);

        for (const name of pageNames) {
            expect(() => surfaceFor(name)).not.toThrow();
        }
    });

    it('uses the admin surface only for admin pages', () => {
        expect(surfaceFor('admin/index')).toBe('admin');
        expect(surfaceFor('settings/profile')).toBeNull();
        expect(surfaceFor('welcome')).toBeNull();
    });

    it('rejects a page outside every layout group', () => {
        expect(() => surfaceFor('dashboard')).toThrow(
            'No layout group for page: dashboard',
        );
    });
});
