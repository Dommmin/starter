import { Link } from '@inertiajs/react';
import { Compass } from 'lucide-react';
import { useTranslation } from '@/i18n';
import { home } from '@/routes';
import { home as localizedHome } from '@/routes/localized';

/**
 * Uploaded logo. Structurally compatible with `App.Data.Media.MediaImageData`
 * plus `alt` (`{ ...media, alt }`), so a DAM image can be passed directly.
 */
export type BrandLogoImage = {
    src: string;
    /** Accessible name of the logo link, e.g. the organisation name. */
    alt: string;
    width?: number;
    height?: number;
    srcset?: string;
};

export type BrandLogoProps = {
    href?: string;
    ariaLabel?: string;
    /** Image logo; falls back to the text logo when absent. */
    image?: BrandLogoImage;
    /**
     * Brand name of the text logo, rendered in one tone (e.g. the site name
     * saved in the panel). Defaults to the two-tone `brand.firstPart` /
     * `brand.secondPart` catalog lines.
     */
    name?: string;
    className?: never;
    style?: never;
};

export function BrandLogo({ href, ariaLabel, image, name }: BrandLogoProps) {
    const { t, locale } = useTranslation();
    const targetHref =
        href ?? (locale === 'en' ? home.url() : localizedHome.url({ locale }));

    return (
        <Link
            href={targetHref}
            prefetch
            aria-label={
                image ? ariaLabel : (ariaLabel ?? name ?? t('brand.name'))
            }
            className="group focus-visible:ring-ring inline-flex min-h-[44px] min-w-0 items-center gap-2.5 rounded-lg text-base font-semibold tracking-tight transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            {image ? (
                <img
                    src={image.src}
                    srcSet={image.srcset || undefined}
                    sizes={image.srcset ? '10rem' : undefined}
                    alt={image.alt}
                    width={image.width}
                    height={image.height}
                    decoding="async"
                    className="block h-9 w-auto max-w-40 object-contain"
                />
            ) : (
                <>
                    <span className="bg-primary text-primary-foreground flex h-9 w-9 shrink-0 items-center justify-center rounded-lg shadow-sm transition-transform duration-300 group-hover:scale-105">
                        <Compass className="h-5 w-5" aria-hidden="true" />
                    </span>
                    {name ? (
                        <span
                            title={name}
                            className="text-foreground min-w-0 truncate font-serif text-base tracking-tight sm:text-lg"
                        >
                            {name}
                        </span>
                    ) : (
                        <span
                            title={t('brand.name')}
                            className="text-foreground min-w-0 truncate font-serif text-base tracking-tight sm:text-lg"
                        >
                            {t('brand.firstPart')}{' '}
                            <span className="text-primary">
                                {t('brand.secondPart')}
                            </span>
                        </span>
                    )}
                </>
            )}
        </Link>
    );
}
