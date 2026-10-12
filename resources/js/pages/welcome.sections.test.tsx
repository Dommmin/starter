import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { HomeSections } from '@/components/home/home-sections';
import { I18nProvider } from '@/i18n';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        prefetch: _prefetch,
        ...props
    }: {
        children: ReactNode;
        href: string;
        prefetch?: boolean;
        [key: string]: unknown;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    router: { on: () => () => {} },
    useForm: (initial: Record<string, string>) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: () => {},
        reset: () => {},
        submit: () => {},
    }),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

type Section = App.Data.Home.HomeSectionData;

const initialPage = {
    props: {
        i18n: {
            area: 'public',
            locale: 'en',
            defaultLocale: 'en',
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
            messages: {
                home: {
                    latestArticlesTitle: 'Latest articles',
                    latestArticlesEmpty: 'No articles yet.',
                    allArticles: 'All articles',
                    readMore: 'Read article: :title',
                    faqEmpty: 'No questions yet.',
                    viewProject: 'View project: :title',
                    priceFrom: 'from :price',
                    priceOnRequest: 'Quote on request',
                    recommended: 'Recommended',
                },
                contact: {
                    title: 'Default contact title',
                    submit: 'Send message',
                    fields: {
                        name: 'Name',
                        email: 'Email',
                        message: 'Message',
                    },
                },
            },
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const sections: Section[] = [
    {
        id: 1,
        type: 'hero',
        anchor: 'hero',
        content: {
            eyebrow: 'Eyebrow',
            title: 'Hero title',
            description: 'Hero description',
            primaryAction: { label: 'Write to us', url: '#contact' },
            secondaryAction: null,
            image: null,
            imageAlt: '',
            stats: [],
        },
    },
    {
        id: 2,
        type: 'features',
        anchor: 'features',
        content: {
            title: 'Features title',
            description: null,
            items: [
                { icon: 'rocket', title: 'Fast', description: 'Very fast' },
            ],
        },
    },
    {
        id: 3,
        type: 'faq',
        anchor: 'faq',
        content: {
            title: 'Questions',
            description: null,
            items: [{ id: 7, question: 'Why?', answer: 'Because.' }],
        },
    },
    {
        id: 4,
        type: 'testimonials',
        anchor: 'testimonials',
        content: {
            title: 'People say',
            items: [{ author: 'Ada', role: 'Engineer', quote: 'Great kit.' }],
            stats: [{ value: '4.9/5', label: 'Average rating' }],
        },
    },
    {
        id: 5,
        type: 'latest_articles',
        anchor: 'latest-articles',
        content: {
            title: 'News',
            items: [
                {
                    title: 'Launch',
                    url: '/articles/launch',
                    excerpt: 'We launched.',
                    publishedAt: null,
                    cover: null,
                    coverAlt: '',
                },
            ],
            listUrl: '/articles',
        },
    },
    {
        id: 6,
        type: 'contact',
        anchor: 'contact',
        content: { title: 'Talk to us', description: null },
    },
    {
        id: 7,
        type: 'cta',
        anchor: 'cta',
        content: {
            title: 'Ready?',
            description: 'Start today.',
            primaryAction: { label: 'Log in', url: '/login' },
            secondaryAction: { label: 'Articles', url: '/articles' },
        },
    },
];

const mountedRoots: Root[] = [];

async function render(list: Section[]): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <HomeSections
                    sections={list}
                    contactForm={{ token: 'token' }}
                    fallbackTitle="Starter site"
                />
            </I18nProvider>,
        );
    });

    return container;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('HomeSections', () => {
    it('renders every section type in the given order under its fixed anchor', async () => {
        const container = await render(sections);

        expect(
            Array.from(container.children).map((element) => element.id),
        ).toEqual([
            'hero',
            'features',
            'faq',
            'testimonials',
            'latest-articles',
            'contact',
            'cta',
        ]);

        const text = container.textContent ?? '';
        for (const expected of [
            'Hero title',
            'Features title',
            'Very fast',
            'Why?',
            'Great kit.',
            'Engineer',
            '4.9/5',
            'Average rating',
            'Launch',
            'Talk to us',
            'Ready?',
        ]) {
            expect(text).toContain(expected);
        }
        expect(container.querySelectorAll('h1')).toHaveLength(1);
        expect(container.querySelector('h1')?.textContent).toBe('Hero title');
        expect(text).not.toContain('Starter site');
        expect(text).not.toContain('Default contact title');
    });

    it('links actions and articles to the URLs resolved by the server', async () => {
        const container = await render(sections);
        const hrefs = Array.from(container.querySelectorAll('a')).map((link) =>
            link.getAttribute('href'),
        );

        expect(hrefs).toEqual(
            expect.arrayContaining([
                '#contact',
                '/articles/launch',
                '/articles',
                '/login',
            ]),
        );
        expect(
            container.querySelector('a[href="/articles/launch"]')?.textContent,
        ).toBe('Launch');
    });

    it('shows empty states for sections without data and nothing without sections', async () => {
        const container = await render([
            {
                id: 3,
                type: 'faq',
                anchor: 'faq',
                content: { title: null, description: null, items: [] },
            },
            {
                id: 5,
                type: 'latest_articles',
                anchor: 'latest-articles',
                content: { title: null, items: [], listUrl: '/articles' },
            },
        ]);

        expect(container.textContent).toContain('No questions yet.');
        expect(container.textContent).toContain('No articles yet.');
        expect(
            Array.from(container.querySelectorAll('h2')).map(
                (heading) => heading.textContent,
            ),
        ).toContain('Latest articles');

        const empty = await render([]);
        expect(empty.textContent).toBe('Starter site');
        expect(empty.querySelectorAll('[id]')).toHaveLength(0);
    });

    it('renders exactly one h1 with the site name when there is no hero', async () => {
        for (const list of [[], sections.filter((s) => s.type !== 'hero')]) {
            const container = await render(list);
            const headings = container.querySelectorAll('h1');

            expect(headings).toHaveLength(1);
            expect(headings[0].textContent).toBe('Starter site');
        }
    });

    it('promotes a lone secondary action to the main action of the section', async () => {
        const container = await render([
            {
                id: 8,
                type: 'cta',
                anchor: 'cta',
                content: {
                    title: 'Ready?',
                    description: null,
                    primaryAction: null,
                    secondaryAction: {
                        label: 'Open admin panel',
                        url: '/admin',
                    },
                },
            },
        ]);

        const actions = container.querySelectorAll('#cta a');
        expect(actions).toHaveLength(1);
        expect(actions[0].textContent).toBe('Open admin panel');
        expect(actions[0].className).toContain('bg-primary');
    });

    it('keeps both actions with the secondary one styled apart', async () => {
        const container = await render([sections[6]]);

        const [main, extra] = Array.from(container.querySelectorAll('#cta a'));
        expect(main.textContent).toBe('Log in');
        expect(main.className).toContain('bg-primary');
        expect(extra.textContent).toBe('Articles');
        expect(extra.className).not.toContain('bg-primary');
    });

    it('renders the hero image eagerly beside the copy and its figures below', async () => {
        const container = await render([
            {
                ...sections[0],
                type: 'hero',
                content: {
                    eyebrow: null,
                    title: 'Hero title',
                    description: null,
                    primaryAction: null,
                    secondaryAction: null,
                    image: {
                        sources: [],
                        src: '/media/hero.jpg',
                        srcset: '/media/hero.jpg 1200w',
                        width: 1200,
                        height: 800,
                    },
                    imageAlt: 'Our studio',
                    stats: [{ value: '300+', label: 'Projects' }],
                },
            },
        ]);

        const image = container.querySelector('#hero img');
        expect(image?.getAttribute('alt')).toBe('Our studio');
        expect(image?.getAttribute('loading')).toBe('eager');
        expect(container.querySelector('#hero dl dt')?.textContent).toBe(
            'Projects',
        );
        // The counting figure keeps its final value readable to assistive technology.
        expect(container.querySelector('#hero dl dd')?.textContent).toContain(
            '300+',
        );
    });

    it('renders portfolio projects with category, thumbnail and an external link', async () => {
        const container = await render([
            {
                id: 9,
                type: 'portfolio',
                anchor: 'portfolio',
                content: {
                    title: 'Our work',
                    description: null,
                    items: [
                        {
                            title: 'Shop',
                            category: 'E-commerce',
                            description: 'Online store',
                            image: {
                                sources: [],
                                src: '/media/shop.jpg',
                                srcset: '/media/shop.jpg 800w',
                                width: 800,
                                height: 450,
                            },
                            imageAlt: 'Shop home page',
                            url: 'https://shop.example.com',
                        },
                        {
                            title: 'Logo',
                            category: null,
                            description: null,
                            image: null,
                            imageAlt: '',
                            url: null,
                        },
                    ],
                },
            },
        ]);

        const section = container.querySelector('#portfolio');
        expect(section?.querySelector('h2')?.textContent).toBe('Our work');
        expect(
            Array.from(section?.querySelectorAll('h3') ?? []).map(
                (heading) => heading.textContent,
            ),
        ).toEqual(['Shop', 'Logo']);
        const link = section?.querySelector('a');
        expect(link?.getAttribute('href')).toBe('https://shop.example.com');
        expect(link?.getAttribute('target')).toBe('_blank');
        expect(link?.getAttribute('rel')).toBe('noopener noreferrer');
        expect(section?.querySelectorAll('img')).toHaveLength(1);
        expect(section?.querySelector('img')?.getAttribute('alt')).toBe(
            'Shop home page',
        );
        expect(section?.textContent).toContain('E-commerce');
    });

    it('renders team members with a square photo, role and skills', async () => {
        const container = await render([
            {
                id: 10,
                type: 'team',
                anchor: 'team',
                content: {
                    title: 'Team',
                    description: null,
                    items: [
                        {
                            name: 'Ada Example',
                            role: 'Developer',
                            bio: 'Builds things.',
                            photo: {
                                sources: [],
                                src: '/media/ada.jpg',
                                srcset: '/media/ada.jpg 600w',
                                width: 600,
                                height: 600,
                            },
                            photoAlt: 'Ada smiling',
                            skills: ['Laravel', 'React'],
                        },
                    ],
                },
            },
        ]);

        const section = container.querySelector('#team');
        expect(section?.querySelector('h3')?.textContent).toBe('Ada Example');
        expect(section?.querySelector('img')?.getAttribute('alt')).toBe(
            'Ada smiling',
        );
        expect(section?.querySelector('.aspect-square img')).not.toBeNull();
        expect(section?.textContent).toContain('Developer');
        expect(section?.textContent).toContain('Laravel');
        expect(section?.textContent).toContain('React');
    });

    it('renders pricing plans with formatted prices, scope and actions', async () => {
        const plan = {
            period: null,
            description: null,
            features: [],
            highlighted: false,
            action: null,
            currency: 'PLN',
            priceFrom: false,
        };
        const container = await render([
            {
                id: 11,
                type: 'pricing',
                anchor: 'pricing',
                content: {
                    title: 'Pricing',
                    description: null,
                    note: 'Prices are net.',
                    items: [
                        {
                            ...plan,
                            name: 'Business card',
                            priceMinor: 290000,
                            priceFrom: true,
                            features: ['Up to 5 pages', 'Contact form'],
                            highlighted: true,
                            action: {
                                label: 'Ask for a quote',
                                url: '#contact',
                            },
                        },
                        { ...plan, name: 'Logo', priceMinor: 49950 },
                        { ...plan, name: 'Shop', priceMinor: null },
                    ],
                },
            },
        ]);

        const section = container.querySelector('#pricing');
        // Intl separates the currency with a no-break space.
        const text = (section?.textContent ?? '').replace(/\u00a0/g, ' ');
        expect(text).toContain('from PLN 2,900');
        expect(text).toContain('PLN 499.50');
        expect(text).toContain('Quote on request');
        expect(text).toContain('Recommended');
        expect(text).toContain('Prices are net.');
        expect(
            Array.from(section?.querySelectorAll('ul li') ?? []).map(
                (item) => item.textContent,
            ),
        ).toEqual(['Up to 5 pages', 'Contact form']);
        expect(section?.querySelector('a')?.getAttribute('href')).toBe(
            '#contact',
        );
        expect(section?.querySelectorAll('a')).toHaveLength(1);
    });
});
