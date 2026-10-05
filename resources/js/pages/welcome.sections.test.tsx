import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { HomeSections } from '@/components/home/home-sections';
import { I18nProvider } from '@/i18n';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
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
});
