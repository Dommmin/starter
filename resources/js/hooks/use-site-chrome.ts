import { usePage } from '@inertiajs/react';
import type {
    BrandLogoImage,
    PublicChromeFooter,
} from '@/design-system/primitives';

export type SiteChrome = {
    logo?: BrandLogoImage;
    footer: PublicChromeFooter;
};

/**
 * `PublicChrome` props built from the shared `site` settings: the DAM logo
 * (with the site name as its accessible name), the copyright line
 * ("© {year} {footer text or site name}"), contact details and social links.
 * Without the shared prop (e.g. a partial fixture) the chrome keeps its own
 * defaults.
 */
export function useSiteChrome(): SiteChrome {
    const site = usePage().props.site as
        | App.Data.Settings.SiteSettingsData
        | undefined;

    if (!site) {
        return { footer: {} };
    }

    const { email, phone, address } = site.contact;

    return {
        logo: site.logo ? { ...site.logo, alt: site.name } : undefined,
        footer: {
            copyright: `© ${new Date().getFullYear()} ${site.footerText ?? site.name}`,
            contact: {
                email: email ?? undefined,
                phone: phone ?? undefined,
                address: address ?? undefined,
            },
            social: site.social.map(({ network, label, url }) => ({
                network,
                label,
                url,
            })),
        },
    };
}
