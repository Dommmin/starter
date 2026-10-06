import { useTranslation } from '@/i18n';
import { cn } from '@/lib/utils';
import { BrandLogo, type BrandLogoImage } from './brand-logo';
import { LocaleSwitcher } from './locale-switcher';
import { useSiteBrand } from './site-brand';

export type HeaderUtilityProps = {
    /** Right-hand actions (account controls, mobile menu trigger). */
    children?: React.ReactNode;
    /**
     * Main navigation, placed next to the logo in its own `nav` landmark;
     * the locale switcher stays with the actions on the right.
     */
    navigation?: React.ReactNode;
    /** Image logo; defaults to the logo of the shared `site` settings. */
    logo?: BrandLogoImage;
    /** One-tone text logo name; defaults to the saved site name. */
    brandName?: string;
    /**
     * Hide the locale switcher below `sm`; the caller then renders it in its
     * mobile menu (see `MobileNav` `utilities`), so a typical site name fits
     * next to the remaining controls at 360 px.
     */
    switchersInMobileMenu?: boolean;
    className?: never;
    style?: never;
};

export function HeaderUtility({
    children,
    navigation,
    logo,
    brandName,
    switchersInMobileMenu = false,
}: HeaderUtilityProps) {
    const { t } = useTranslation();
    const siteBrand = useSiteBrand();

    return (
        <header className="border-border-subtle bg-background/90 relative z-10 border-b backdrop-blur-md">
            <a
                href="#main-content"
                className="focus:bg-primary focus:text-primary-foreground focus:ring-ring sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:px-4 focus:py-2 focus:shadow-md focus:ring-2 focus:outline-none"
            >
                {t('a11y.skipToContent')}
            </a>
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:h-20 sm:px-6 lg:px-8">
                <div className="flex min-w-0 items-center gap-8">
                    <BrandLogo
                        image={logo ?? siteBrand.logo}
                        name={brandName ?? siteBrand.brandName}
                    />

                    {navigation && (
                        <nav
                            aria-label={t('a11y.mainNavigation')}
                            className="hidden lg:block"
                        >
                            {navigation}
                        </nav>
                    )}
                </div>

                <div className="flex shrink-0 items-center gap-1.5 sm:gap-3">
                    <div
                        className={cn(
                            'items-center',
                            switchersInMobileMenu ? 'hidden sm:flex' : 'flex',
                        )}
                    >
                        <LocaleSwitcher />
                    </div>
                    {children}
                </div>
            </div>
        </header>
    );
}
