import { describe, expect, it } from 'vitest';
import {
    NONE,
    toContent,
    toFieldErrors,
    toFormValues,
} from './section-form-values';

type Section = App.Data.Admin.HomeSections.HomeSectionFormData;

const hero: Section = {
    id: 1,
    locale: 'en',
    enabled: true,
    updatedAt: null,
    type: 'hero',
    content: {
        title: 'Hero',
        eyebrow: null,
        description: 'Intro',
        primaryAction: { label: 'About', target: 'page', pageId: 4 },
        secondaryAction: null,
    },
};

describe('home section form values', () => {
    it('round-trips hero content with buttons', () => {
        const values = toFormValues(hero);

        expect(values.primaryTarget).toBe('page');
        expect(values.primaryPageId).toBe('4');
        expect(values.secondaryTarget).toBe(NONE);
        expect(toContent('hero', values)).toEqual({
            eyebrow: null,
            title: 'Hero',
            description: 'Intro',
            primaryAction: { label: 'About', target: 'page', pageId: 4 },
            secondaryAction: null,
        });
    });

    it('sends feature items with a known icon or none', () => {
        const values = toFormValues({
            ...hero,
            type: 'features',
            content: {
                title: null,
                description: null,
                items: [{ icon: null, title: 'Fast', description: 'Very' }],
            },
        });

        expect(values.items).toEqual([
            { icon: NONE, title: 'Fast', description: 'Very' },
        ]);
        expect(
            toContent('features', {
                ...values,
                items: [
                    ...values.items,
                    { icon: 'rocket', title: 'B', description: 'C' },
                ],
            }),
        ).toEqual({
            title: null,
            description: null,
            items: [
                { icon: null, title: 'Fast', description: 'Very' },
                { icon: 'rocket', title: 'B', description: 'C' },
            ],
        });
    });

    it('maps backend error keys to editor fields', () => {
        expect(
            toFieldErrors({
                'content.title': 'Title',
                'content.primaryAction.label': 'Label',
                'content.secondaryAction.pageId': 'Page',
                'content.primaryAction': 'Action',
                'content.items.2.title': 'Item',
                conflict: 'Conflict',
            }),
        ).toEqual({
            title: 'Title',
            primaryLabel: 'Label',
            secondaryPageId: 'Page',
            primaryTarget: 'Action',
            'items.2.title': 'Item',
            conflict: 'Conflict',
        });
    });
});
