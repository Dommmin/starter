import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import type { ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { Seo, serializeJsonLd } from './seo';

const headCalls: { title?: string; children: ReactNode }[] = [];

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            seo: {
                siteName: 'Starter',
                canonical: 'https://example.test/current',
                defaultImage: 'https://example.test/og.png',
                organization: {
                    name: 'Starter',
                    url: 'https://example.test',
                    logo: null,
                },
            },
        },
    }),
    router: {
        on: () => () => {},
    },
    Head: ({ title, children }: { title?: string; children: ReactNode }) => {
        headCalls.push({ title, children });

        return <>{children}</>;
    },
}));

const initialPage = {
    props: {
        i18n: {
            area: 'public',
            locale: 'pl',
            defaultLocale: 'en',
            messages: {},
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

function renderHead(node: ReactNode): { html: string; title?: string } {
    headCalls.length = 0;
    const html = renderToStaticMarkup(
        <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
    );

    return { html, title: headCalls.at(-1)?.title };
}

describe('Seo', () => {
    it('renders title, description, canonical, hreflang, Open Graph and robots with head keys', () => {
        const { html, title } = renderHead(
            <Seo
                title="O nas"
                description="Opis strony"
                canonical="https://example.test/pl/o-nas"
                alternates={{
                    en: 'https://example.test/about',
                    pl: 'https://example.test/pl/o-nas',
                    'x-default': 'https://example.test/about',
                }}
                type="article"
                publishedAt="2026-09-01T10:00:00+00:00"
            />,
        );

        expect(title).toBe('O nas');
        expect(html).toContain(
            '<meta head-key="description" name="description" content="Opis strony"/>',
        );
        expect(html).toContain(
            '<link head-key="canonical" rel="canonical" href="https://example.test/pl/o-nas"/>',
        );
        expect(html).toContain(
            '<link head-key="alternate:x-default" rel="alternate" hrefLang="x-default" href="https://example.test/about"/>',
        );
        expect(html).toContain('hrefLang="en"');
        expect(html).toContain('property="og:type" content="article"');
        expect(html).toContain('property="og:locale" content="pl"');
        expect(html).toContain(
            'property="og:url" content="https://example.test/pl/o-nas"',
        );
        expect(html).toContain(
            'property="og:image" content="https://example.test/og.png"',
        );
        expect(html).toContain(
            'property="article:published_time" content="2026-09-01T10:00:00+00:00"',
        );
        expect(html).toContain(
            'name="twitter:card" content="summary_large_image"',
        );
        expect(html).toContain('name="robots" content="index,follow"');
    });

    it('falls back to the shared canonical URL', () => {
        const { html } = renderHead(<Seo title="Home" />);

        expect(html).toContain(
            'rel="canonical" href="https://example.test/current"',
        );
        expect(html).not.toContain('name="description"');
    });

    it('omits canonical and hreflang on noindex pages', () => {
        const { html } = renderHead(
            <Seo
                title="Not found"
                robots="noindex,nofollow"
                alternates={{ en: 'https://example.test/' }}
            />,
        );

        expect(html).toContain('name="robots" content="noindex,nofollow"');
        expect(html).not.toContain('rel="canonical"');
        expect(html).not.toContain('rel="alternate"');
    });

    it('renders JSON-LD that cannot break out of its script element', () => {
        const { html } = renderHead(
            <Seo
                title="Page"
                jsonLd={{
                    '@type': 'WebPage',
                    name: '</script><script>alert(1)</script>',
                }}
            />,
        );

        expect(html).toContain(
            '<script head-key="json-ld" type="application/ld+json">',
        );
        expect(html).not.toContain('</script><script>alert(1)');
        expect(html).toContain('\\u003c/script\\u003e');
    });
});

describe('serializeJsonLd', () => {
    it('escapes HTML-significant characters and keeps valid JSON', () => {
        const data = [{ name: 'a<b>&c\u2028' }];
        const serialized = serializeJsonLd(data);

        expect(serialized).not.toMatch(/[<>&\u2028]/);
        expect(JSON.parse(serialized)).toEqual(data);
    });
});
