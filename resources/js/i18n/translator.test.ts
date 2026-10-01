import { describe, expect, it } from 'vitest';
import { createTranslator, interpolate } from './translator';

const messages = {
    common: {
        greeting: 'Hello :name',
        braces: 'Hello {name} and {{other}}',
        range: 'Showing :from–:to of :total pages',
        nested: { title: 'Nested' },
        empty: '',
        list: ['a', 'b'],
        items: {
            one: ':count item',
            few: ':count items (few)',
            many: ':count items (many)',
            other: ':count items',
        },
        withZero: {
            zero: 'No items',
            one: ':count item',
            other: ':count items',
        },
    },
};

describe('interpolate', () => {
    it('fills :param, {param} and {{param}} placeholders exactly once', () => {
        expect(
            interpolate('Showing :from–:to of :total', {
                from: 1,
                to: ':total',
                total: 9,
            }),
        ).toBe('Showing 1–:total of 9');
        expect(interpolate('Hi {name} {{name}}', { name: 'Ann' })).toBe(
            'Hi Ann Ann',
        );
    });

    it('leaves placeholders without a param as written', () => {
        expect(interpolate(':language (:state)', { language: 'PL' })).toBe(
            'PL (:state)',
        );
    });
});

describe('createTranslator', () => {
    const translate = createTranslator('en', messages);

    it('resolves dot-separated keys and interpolates params', () => {
        expect(translate('common.nested.title')).toBe('Nested');
        expect(translate('common.greeting', { name: 'Ann' })).toBe('Hello Ann');
        expect(translate('common.braces', { name: 'A', other: 'B' })).toBe(
            'Hello A and B',
        );
    });

    it('returns the key for missing, empty and non-string values', () => {
        expect(translate('common.missing')).toBe('common.missing');
        expect(translate('missing.deep.key')).toBe('missing.deep.key');
        expect(translate('common.empty')).toBe('common.empty');
        expect(translate('common.nested')).toBe('common.nested');
        expect(translate('common.list')).toBe('common.list');
        expect(translate('common.items')).toBe('common.items');
    });

    it('picks the plural form by locale rules and fills :count', () => {
        expect(translate('common.items', {}, 1)).toBe('1 item');
        expect(translate('common.items', {}, 5)).toBe('5 items');

        const pl = createTranslator('pl', messages);
        expect(pl('common.items', {}, 1)).toBe('1 item');
        expect(pl('common.items', {}, 3)).toBe('3 items (few)');
        expect(pl('common.items', {}, 5)).toBe('5 items (many)');
    });

    it('prefers an explicit zero form and falls back to other', () => {
        expect(translate('common.withZero', {}, 0)).toBe('No items');
        expect(translate('common.items', {}, 0)).toBe('0 items');
        expect(createTranslator('pl', messages)('common.withZero', {}, 3)).toBe(
            '3 items',
        );
    });

    it('survives an invalid locale', () => {
        expect(
            createTranslator('not a locale', messages)('common.items', {}, 2),
        ).toBe('2 items');
    });
});
