import type { BrandLogoImage, NavItem } from '@/design-system/primitives';
import type { TranslationFunction } from '@/i18n';

/** Existing public assets stand in for DAM images; nothing is uploaded. */
export const demoImage = {
    src: '/apple-touch-icon.png',
    width: 180,
    height: 180,
} as const;

/** Synthetic DAM image payload (`App.Data.Media.MediaImageData`). */
export const demoMediaImage: App.Data.Media.MediaImageData = {
    sources: [],
    src: demoImage.src,
    srcset: '',
    width: demoImage.width,
    height: demoImage.height,
};

export const demoLogo: BrandLogoImage = {
    src: demoImage.src,
    alt: 'Demo',
    width: demoImage.width,
    height: demoImage.height,
};

/**
 * Header menu of the showcase page: every `NavItem` kind, a submenu whose
 * parent is a link, a `group` with children, an external link opening in a
 * new tab and a long label. Anchors point at the showcase families.
 */
export function demoNavItems(t: TranslationFunction): NavItem[] {
    const demo = (key: string) => t(`admin.designSystem.web.nav.${key}`);

    return [
        {
            id: 'sections',
            label: demo('sections'),
            href: '#web-01',
            kind: 'anchor',
            children: [
                {
                    id: 'web-02',
                    label: demo('landing'),
                    href: '#web-02',
                    kind: 'anchor',
                },
                {
                    id: 'web-04',
                    label: demo('content'),
                    href: '#web-04',
                    kind: 'anchor',
                },
                {
                    id: 'web-05',
                    label: demo('contact'),
                    href: '#web-05',
                    kind: 'anchor',
                },
            ],
        },
        {
            id: 'resources',
            label: demo('resources'),
            kind: 'group',
            children: [
                {
                    id: 'articles',
                    label: demo('articles'),
                    href: '/articles',
                    kind: 'internal',
                },
                {
                    id: 'docs',
                    label: demo('externalDocs'),
                    href: 'https://laravel.com/docs',
                    kind: 'external',
                    newTab: true,
                },
            ],
        },
        { id: 'home', label: demo('home'), href: '/', kind: 'internal' },
        {
            id: 'long',
            label: demo('longLabel'),
            href: '#web-seo',
            kind: 'anchor',
        },
    ];
}

/** Footer columns: a group, a single link and an external link. */
export function demoFooterGroups(t: TranslationFunction): NavItem[] {
    const demo = (key: string) => t(`admin.designSystem.web.nav.${key}`);

    return [
        {
            id: 'footer-sections',
            label: demo('sections'),
            kind: 'group',
            children: [
                {
                    id: 'f-web-02',
                    label: demo('landing'),
                    href: '#web-02',
                    kind: 'anchor',
                },
                {
                    id: 'f-web-05',
                    label: demo('contact'),
                    href: '#web-05',
                    kind: 'anchor',
                },
            ],
        },
        {
            id: 'footer-resources',
            label: demo('resources'),
            kind: 'group',
            children: [
                {
                    id: 'f-articles',
                    label: demo('articles'),
                    href: '/articles',
                    kind: 'internal',
                },
                {
                    id: 'f-docs',
                    label: demo('externalDocs'),
                    href: 'https://laravel.com/docs',
                    kind: 'external',
                    newTab: true,
                },
            ],
        },
    ];
}
