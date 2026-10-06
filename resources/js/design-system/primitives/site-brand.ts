import { usePage } from '@inertiajs/react';
import type { BrandLogoImage } from './brand-logo';

/*
 * Internal to the design system (not re-exported from the barrel): the
 * public header, footer and auth header resolve the brand the same way.
 */

export type SiteBrand = {
    /** Uploaded site logo with the site name as its accessible name. */
    logo?: BrandLogoImage;
    /** Saved site name; undefined keeps the two-tone catalog text logo. */
    brandName?: string;
};

/**
 * Brand of the shared `site` settings. The saved name replaces the catalog
 * text logo only once the settings were saved (`site.isCustomized`).
 */
export function useSiteBrand(): SiteBrand {
    const site = usePage().props.site as
        | App.Data.Settings.SiteSettingsData
        | undefined;

    return {
        logo: site?.logo ? { ...site.logo, alt: site.name } : undefined,
        brandName: site?.isCustomized ? site.name : undefined,
    };
}
