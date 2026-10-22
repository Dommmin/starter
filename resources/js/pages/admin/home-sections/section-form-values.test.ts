import { describe, expect, it } from 'vite-plus/test';
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
        imageId: 7,
        stats: [{ value: '300+', label: 'Projects' }],
    },
};

describe('home section form values', () => {
    it('round-trips hero content with buttons', () => {
        const values = toFormValues(hero);

        expect(values.primaryTarget).toBe('page');
        expect(values.primaryPageId).toBe('4');
        expect(values.secondaryTarget).toBe(NONE);
        expect(values.imageId).toBe('7');
        expect(toContent('hero', values)).toEqual({
            eyebrow: null,
            title: 'Hero',
            description: 'Intro',
            primaryAction: { label: 'About', target: 'page', pageId: 4 },
            secondaryAction: null,
            imageId: 7,
            stats: [{ value: '300+', label: 'Projects' }],
        });
    });

    it('sends a removed hero image as null', () => {
        const values = { ...toFormValues(hero), imageId: '' };

        expect(toContent('hero', values)).toMatchObject({ imageId: null });
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

    it('round-trips portfolio projects with optional fields as null', () => {
        const values = toFormValues({
            ...hero,
            type: 'portfolio',
            content: {
                title: 'Work',
                description: null,
                items: [
                    {
                        title: 'Shop',
                        category: null,
                        description: null,
                        imageId: 3,
                        url: null,
                    },
                ],
            },
        });

        expect(values.items).toEqual([
            {
                imageId: '3',
                title: 'Shop',
                category: '',
                description: '',
                url: '',
            },
        ]);
        expect(toContent('portfolio', values)).toEqual({
            title: 'Work',
            description: null,
            items: [
                {
                    title: 'Shop',
                    category: null,
                    description: null,
                    imageId: 3,
                    url: null,
                },
            ],
        });
    });

    it('edits team skills as one comma separated field', () => {
        const values = toFormValues({
            ...hero,
            type: 'team',
            content: {
                title: null,
                description: null,
                items: [
                    {
                        name: 'Ada',
                        role: 'Developer',
                        bio: null,
                        photoId: null,
                        skills: ['Laravel', 'React'],
                    },
                ],
            },
        });

        expect(values.items[0].skills).toBe('Laravel, React');

        values.items[0].skills = ' Laravel ,React,, Figma\n';
        expect(toContent('team', values)).toMatchObject({
            items: [{ skills: ['Laravel', 'React', 'Figma'], photoId: null }],
        });
    });

    it('shows a skill error on the skills field of its member', () => {
        expect(
            toFieldErrors({
                'content.items.0.skills.2': 'Too long.',
                'content.items.0.skills.3': 'Also too long.',
            }),
        ).toEqual({ 'items.0.skills': 'Too long.' });
    });

    it('round-trips pricing plans with prices in the smallest unit', () => {
        const values = toFormValues({
            ...hero,
            type: 'pricing',
            content: {
                title: 'Pricing',
                description: null,
                note: null,
                items: [
                    {
                        name: 'Start',
                        priceMinor: 290050,
                        currency: 'PLN',
                        priceFrom: true,
                        period: null,
                        description: null,
                        features: ['Five pages', 'Contact form, maps'],
                        highlighted: true,
                        action: { label: 'Ask', target: 'page', pageId: 4 },
                    },
                ],
            },
        });

        expect(values.items[0]).toMatchObject({
            price: '2900.50',
            priceMode: 'from',
            highlighted: 'yes',
            actionPageId: '4',
        });
        expect(toContent('pricing', values)).toEqual({
            title: 'Pricing',
            description: null,
            note: null,
            items: [
                {
                    name: 'Start',
                    priceMinor: 290050,
                    currency: 'PLN',
                    priceFrom: true,
                    period: null,
                    description: null,
                    features: ['Five pages', 'Contact form, maps'],
                    highlighted: true,
                    action: { label: 'Ask', target: 'page', pageId: 4 },
                },
            ],
        });
    });

    it('parses entered prices and keeps invalid ones for the backend', () => {
        const values = toFormValues({
            ...hero,
            type: 'pricing',
            content: { title: null, description: null, note: null, items: [] },
        });
        const plan = {
            name: 'P',
            currency: 'PLN',
            priceMode: 'exact',
            highlighted: 'no',
            actionTarget: NONE,
        };
        const priceOf = (price: string) =>
            (
                toContent('pricing', { ...values, items: [{ ...plan, price }] })
                    .items as { priceMinor: unknown; action: unknown }[]
            )[0];

        expect(priceOf('2 900,5').priceMinor).toBe(290050);
        expect(priceOf('').priceMinor).toBeNull();
        expect(priceOf('abc').priceMinor).toBe('abc');
        expect(priceOf('10').action).toBeNull();
    });

    it('shows plan errors on the fields that edit them', () => {
        expect(
            toFieldErrors({
                'content.items.0.priceMinor': 'Invalid price.',
                'content.items.1.action.label': 'Label required.',
                'content.items.1.action.pageId': 'Page required.',
                'content.items.2.features.4': 'Too long.',
            }),
        ).toEqual({
            'items.0.price': 'Invalid price.',
            'items.1.actionLabel': 'Label required.',
            'items.1.actionPageId': 'Page required.',
            'items.2.features': 'Too long.',
        });
    });
});
