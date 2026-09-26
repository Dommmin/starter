import { usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/i18n';
import { home } from '@/routes';
import { home as localizedHome } from '@/routes/localized';

export type LocaleSwitcherProps = {
    className?: never;
    style?: never;
};

export function LocaleSwitcher() {
    const { t, locale, defaultLocale, availableLocales } = useTranslation();
    const page = usePage();
    const alternateUrls =
        (page.props as { i18n?: { alternateUrls?: Record<string, string> } })
            .i18n?.alternateUrls ?? {};

    if (availableLocales.length <= 1) {
        return null;
    }

    const currentLocale = availableLocales.find((l) => l.code === locale);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="hover:bg-surface-subtle focus-visible:ring-ring text-text-subtle hover:text-foreground flex min-h-[44px] cursor-pointer items-center rounded-full px-2.5 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none sm:px-3 sm:text-sm"
                    aria-label={t('a11y.languageSelector')}
                >
                    <Globe className="h-4 w-4 sm:mr-1.5" aria-hidden="true" />
                    <span className="hidden sm:inline">
                        {currentLocale?.native ?? locale.toUpperCase()}
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {availableLocales.map((loc) => {
                    const targetUrl =
                        alternateUrls[loc.code] ??
                        (loc.code === defaultLocale
                            ? home.url()
                            : localizedHome.url({ locale: loc.code }));
                    const isCurrent = loc.code === locale;

                    return (
                        <DropdownMenuItem key={loc.code} asChild>
                            <a
                                href={targetUrl}
                                className="flex w-full items-center justify-between px-2.5 py-1.5 text-sm"
                            >
                                <span>{loc.native}</span>
                                {isCurrent && (
                                    <span className="text-primary ml-2 font-semibold">
                                        ✓
                                    </span>
                                )}
                            </a>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
