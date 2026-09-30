import { useTranslation } from '@/i18n';
import { cn } from '@/lib/utils';
import { BrandLogo, type BrandLogoImage } from './brand-logo';
import { LocaleSwitcher } from './locale-switcher';
import { ThemeSwitcher } from './theme-switcher';

export type HeaderUtilityProps = {
    children?: React.ReactNode;
    /** Optional image logo; the text logo otherwise. */
    logo?: BrandLogoImage;
    /** One-tone text logo name; the catalog brand otherwise. */
    brandName?: string;
    /**
     * Hide the theme and locale switchers below `sm`; the caller then
     * renders them in its mobile menu (see `MobileNav` `utilities`), so a
     * typical site name fits next to the remaining controls at 360 px.
     */
    switchersInMobileMenu?: boolean;
    className?: never;
    style?: never;
};

export function HeaderUtility({
    children,
    logo,
    brandName,
    switchersInMobileMenu = false,
}: HeaderUtilityProps) {
    const { t } = useTranslation();

    return (
        <header className="border-border-subtle bg-background/90 relative z-10 border-b backdrop-blur-md">
            <a
                href="#main-content"
                className="focus:bg-primary focus:text-primary-foreground focus:ring-ring sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:px-4 focus:py-2 focus:shadow-md focus:ring-2 focus:outline-none"
            >
                {t('a11y.skipToContent')}
            </a>
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-4 sm:h-20 sm:px-6 lg:px-8">
                <BrandLogo image={logo} name={brandName} />

                <nav
                    aria-label={t('a11y.mainNavigation')}
                    className="flex shrink-0 items-center gap-1.5 sm:gap-3"
                >
                    <div
                        className={cn(
                            'items-center gap-1.5 sm:gap-3',
                            switchersInMobileMenu ? 'hidden sm:flex' : 'flex',
                        )}
                    >
                        <ThemeSwitcher />
                        <LocaleSwitcher />
                    </div>
                    {children}
                </nav>
            </div>
        </header>
    );
}
