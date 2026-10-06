import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n';
import type { BrandLogoImage } from './brand-logo';
import { Footer, type FooterContact, type FooterSocialLink } from './footer';
import type { NavItem } from './nav-item';
import { PublicHeader } from './public-header';
import { useSiteBrand } from './site-brand';
import { ThemeSwitcher } from './theme-switcher';

export type PublicChromeFooter = {
    /**
     * Defaults to "© {year} {footer text or name}" from the shared `site`
     * settings, then "© {year} {brand.name}" (common catalog).
     */
    copyright?: string;
    /** Defaults to the shared `navigation.footer` menu of public pages. */
    groups?: NavItem[];
    /** Defaults to the contact details of the shared `site` settings. */
    contact?: FooterContact;
    /** Defaults to the social links of the shared `site` settings. */
    social?: FooterSocialLink[];
};

export type PublicChromeProps = {
    /** Defaults to the shared `navigation.header` menu of public pages. */
    navItems?: NavItem[];
    footer?: PublicChromeFooter;
    /**
     * Optional image logo shown in the header and footer. Defaults to the
     * logo of the shared `site` settings.
     */
    logo?: BrandLogoImage;
    /** Page content, rendered inside `<main id="main-content">`. */
    children: ReactNode;
    className?: never;
    style?: never;
};

function withoutNull<Value>(
    value: Value | null | undefined,
): Value | undefined {
    return value ?? undefined;
}

/**
 * Frame of every public page: header (brand, menu, locale switcher, auth
 * controls with the theme choice for signed-in users), the `main` landmark
 * targeted by the skip link, and the footer with the theme switcher for
 * guests. Menus come from the shared `navigation` prop (managed in
 * the admin panel) unless the page passes its own items.
 *
 * Brand, logo, copyright, contact details and social links default to the
 * shared `site` prop (site settings); a page may override each of them. The
 * saved site name replaces the two-tone catalog text logo only once the
 * settings were saved (`site.isCustomized`).
 */
export function PublicChrome({
    navItems,
    footer,
    logo,
    children,
}: PublicChromeProps) {
    const { t } = useTranslation();
    const { auth, navigation, site: sharedSite } = usePage().props;
    const site = sharedSite as App.Data.Settings.SiteSettingsData | undefined;

    const siteBrand = useSiteBrand();
    const resolvedLogo = logo ?? siteBrand.logo;
    const brandName = siteBrand.brandName;
    const copyrightHolder = site
        ? (site.footerText ?? site.name)
        : t('brand.name');
    const copyright =
        footer?.copyright ?? `© ${new Date().getFullYear()} ${copyrightHolder}`;
    const contact: FooterContact | undefined =
        footer?.contact ??
        (site
            ? {
                  email: withoutNull(site.contact.email),
                  phone: withoutNull(site.contact.phone),
                  address: withoutNull(site.contact.address),
              }
            : undefined);
    const social: FooterSocialLink[] | undefined =
        footer?.social ??
        site?.social.map(({ network, label, url }) => ({
            network,
            label,
            url,
        }));

    return (
        <div className="flex min-h-svh flex-col">
            <PublicHeader
                navItems={navItems ?? navigation?.header}
                logo={resolvedLogo}
                brandName={brandName}
            />

            <main id="main-content" className="flex-1">
                {children}
            </main>

            <Footer
                copyright={copyright}
                groups={footer?.groups ?? navigation?.footer}
                contact={contact}
                social={social}
                logo={resolvedLogo}
                brandName={brandName}
                trailing={auth?.user ? undefined : <ThemeSwitcher withLabel />}
            />
        </div>
    );
}
