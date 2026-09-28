import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n';
import type { BrandLogoImage } from './brand-logo';
import { Footer, type FooterContact, type FooterSocialLink } from './footer';
import type { NavItem } from './nav-item';
import { PublicHeader } from './public-header';

export type PublicChromeFooter = {
    /**
     * Defaults to "© {year} {brand.name}" (common catalog, available in every
     * area). Public content pages pass their `landing.footerCopy` line.
     */
    copyright?: string;
    /** Defaults to the shared `navigation.footer` menu of public pages. */
    groups?: NavItem[];
    contact?: FooterContact;
    social?: FooterSocialLink[];
};

export type PublicChromeProps = {
    /** Defaults to the shared `navigation.header` menu of public pages. */
    navItems?: NavItem[];
    footer?: PublicChromeFooter;
    /** Optional image logo shown in the header and footer. */
    logo?: BrandLogoImage;
    /** Page content, rendered inside `<main id="main-content">`. */
    children: ReactNode;
    className?: never;
    style?: never;
};

/**
 * Frame of every public page: header (brand, theme and locale switchers,
 * auth controls, navigation), the `main` landmark targeted by the skip link,
 * and the footer. Menus come from the shared `navigation` prop (managed in
 * the admin panel) unless the page passes its own items.
 */
export function PublicChrome({
    navItems,
    footer,
    logo,
    children,
}: PublicChromeProps) {
    const { t } = useTranslation();
    const { navigation } = usePage().props;
    const copyright =
        footer?.copyright ?? `© ${new Date().getFullYear()} ${t('brand.name')}`;

    return (
        <>
            <PublicHeader
                navItems={navItems ?? navigation?.header}
                logo={logo}
            />

            <main id="main-content">{children}</main>

            <Footer
                copyright={copyright}
                groups={footer?.groups ?? navigation?.footer}
                contact={footer?.contact}
                social={footer?.social}
                logo={logo}
            />
        </>
    );
}
