import { Link } from '@inertiajs/react';
import { Compass } from 'lucide-react';
import { useTranslation } from '@/i18n';
import { home } from '@/routes';
import { home as localizedHome } from '@/routes/localized';

export type BrandLogoProps = {
    href?: string;
    ariaLabel?: string;
    className?: never;
    style?: never;
};

export function BrandLogo({ href, ariaLabel }: BrandLogoProps) {
    const { t, locale } = useTranslation();
    const targetHref =
        href ?? (locale === 'en' ? home.url() : localizedHome.url({ locale }));

    return (
        <Link
            href={targetHref}
            prefetch
            aria-label={ariaLabel ?? t('brand.name')}
            className="group focus-visible:ring-ring inline-flex min-h-[44px] items-center gap-2.5 rounded-lg text-base font-semibold tracking-tight transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            <span className="bg-primary text-primary-foreground flex h-9 w-9 items-center justify-center rounded-lg shadow-sm transition-transform duration-300 group-hover:scale-105">
                <Compass className="h-5 w-5" aria-hidden="true" />
            </span>
            <span className="text-foreground font-serif text-lg tracking-tight">
                {t('brand.firstPart')}{' '}
                <span className="text-primary">{t('brand.secondPart')}</span>
            </span>
        </Link>
    );
}
