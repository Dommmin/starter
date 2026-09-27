import { describe, expect, it } from 'vitest';
import { convertPlaceholders } from './adapter';

describe('convertPlaceholders', () => {
    it('converts Laravel :params to i18next placeholders exactly once', () => {
        expect(convertPlaceholders('Showing :from–:to of :total pages')).toBe(
            'Showing {{from}}–{{to}} of {{total}} pages',
        );
        expect(convertPlaceholders(':language (:state)')).toBe(
            '{{language}} ({{state}})',
        );
    });

    it('converts single-brace {params} and keeps existing double braces', () => {
        expect(convertPlaceholders('Hello {name}')).toBe('Hello {{name}}');
        expect(convertPlaceholders('Hello {{name}}')).toBe('Hello {{name}}');
    });
});
