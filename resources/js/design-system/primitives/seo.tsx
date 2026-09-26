import { Head, usePage } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { useTranslation } from '@/i18n';

export type SeoRobots =
    | 'index,follow'
    | 'index,nofollow'
    | 'noindex,follow'
    | 'noindex,nofollow';

export type SeoType = 'website' | 'article';

/** A JSON-LD node (schema.org object) — serialised with `serializeJsonLd`. */
export type SeoJsonLd = Record<string, unknown>;

export type SeoProps = {
    /** Page title without the site suffix; `createInertiaApp({ title })` appends it. */
    title: string;
    description?: string | null;
    /** Absolute canonical URL. Defaults to the shared `seo.canonical` (current URL without query). */
    canonical?: string;
    /** hreflang (locale code or `x-default`) => absolute URL of each published language version. */
    alternates?: Record<string, string>;
    robots?: SeoRobots;
    /** Absolute og:image URL. Defaults to the shared `seo.defaultImage`. */
    image?: string | null;
    type?: SeoType;
    /** ISO 8601 publication date, emitted as `article:published_time` for articles. */
    publishedAt?: string | null;
    jsonLd?: SeoJsonLd | SeoJsonLd[];
    className?: never;
    style?: never;
};

/**
 * Serialise JSON-LD for an inline `<script type="application/ld+json">`.
 * `<`, `>`, `&` and the JS line separators are escaped as Unicode escapes,
 * so values such as `</script>` can never close the script element.
 */
export function serializeJsonLd(data: SeoJsonLd | SeoJsonLd[]): string {
    return JSON.stringify(data)
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/&/g, '\\u0026')
        .replace(/\u2028/g, '\\u2028')
        .replace(/\u2029/g, '\\u2029');
}

function toOgLocale(locale: string): string {
    return locale.replace('-', '_');
}

/**
 * Document head for SEO: title, description, canonical, hreflang
 * alternates, Open Graph, Twitter card, robots and JSON-LD. Every tag has a
 * `head-key`, so a page-level `Seo` replaces defaults instead of duplicating
 * them, and the tags are part of the first SSR HTML. Noindex pages get no
 * canonical or hreflang links.
 */
export function Seo({
    title,
    description,
    canonical,
    alternates = {},
    robots = 'index,follow',
    image,
    type = 'website',
    publishedAt,
    jsonLd,
}: SeoProps) {
    const { props } = usePage();
    const { locale } = useTranslation();
    const defaults = props.seo;
    const isIndexable = robots.startsWith('index');
    const canonicalUrl = canonical ?? defaults.canonical;
    const imageUrl = image ?? defaults.defaultImage;

    const tags: ReactElement[] = [
        <meta key="robots" head-key="robots" name="robots" content={robots} />,
        <meta
            key="og:site_name"
            head-key="og:site_name"
            property="og:site_name"
            content={defaults.siteName}
        />,
        <meta
            key="og:title"
            head-key="og:title"
            property="og:title"
            content={title}
        />,
        <meta
            key="og:type"
            head-key="og:type"
            property="og:type"
            content={type}
        />,
        <meta
            key="og:locale"
            head-key="og:locale"
            property="og:locale"
            content={toOgLocale(locale)}
        />,
        <meta
            key="twitter:card"
            head-key="twitter:card"
            name="twitter:card"
            content={imageUrl ? 'summary_large_image' : 'summary'}
        />,
    ];

    if (description) {
        tags.push(
            <meta
                key="description"
                head-key="description"
                name="description"
                content={description}
            />,
            <meta
                key="og:description"
                head-key="og:description"
                property="og:description"
                content={description}
            />,
        );
    }

    if (isIndexable) {
        tags.push(
            <link
                key="canonical"
                head-key="canonical"
                rel="canonical"
                href={canonicalUrl}
            />,
            <meta
                key="og:url"
                head-key="og:url"
                property="og:url"
                content={canonicalUrl}
            />,
            ...Object.entries(alternates).map(([hreflang, href]) => (
                <link
                    key={`alternate:${hreflang}`}
                    head-key={`alternate:${hreflang}`}
                    rel="alternate"
                    hrefLang={hreflang}
                    href={href}
                />
            )),
        );
    }

    if (imageUrl) {
        tags.push(
            <meta
                key="og:image"
                head-key="og:image"
                property="og:image"
                content={imageUrl}
            />,
        );
    }

    if (type === 'article' && publishedAt) {
        tags.push(
            <meta
                key="article:published_time"
                head-key="article:published_time"
                property="article:published_time"
                content={publishedAt}
            />,
        );
    }

    if (jsonLd) {
        tags.push(
            <script
                key="json-ld"
                head-key="json-ld"
                type="application/ld+json"
                dangerouslySetInnerHTML={{ __html: serializeJsonLd(jsonLd) }}
            />,
        );
    }

    return <Head title={title}>{tags}</Head>;
}
