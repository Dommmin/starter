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
    /**
     * `icon` (default): compact globe button with the language code
     * (`PL`), named by `aria-label` with the native language name.
     * `labelled`: full-width row with the icon and a visible
     * „Language: English” caption that is the button's accessible name.
     */
    variant?: 'icon' | 'labelled';
    className?: never;
    style?: never;
};

export function LocaleSwitcher({ variant = 'icon' }: LocaleSwitcherProps) {
    const { t, locale, defaultLocale, availableLocales } = useTranslation();
    const page = usePage();
    const alternateUrls =
        (page.props as { i18n?: { alternateUrls?: Record<string, string> } })
            .i18n?.alternateUrls ?? {};

    if (availableLocales.length <= 1) {
        return null;
    }

    const currentLocale = availableLocales.find((l) => l.code === locale);
    const currentLocaleName = currentLocale?.native ?? locale.toUpperCase();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {variant === 'labelled' ? (
                    <button
                        type="button"
                        className="focus-visible:ring-ring text-foreground hover:bg-surface-subtle inline-flex min-h-[44px] w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <Globe
                            className="h-4 w-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{`${t('language.label')}: ${currentLocaleName}`}</span>
                    </button>
                ) : (
                    <button
                        type="button"
                        className="hover:bg-surface-subtle focus-visible:ring-ring text-text-subtle hover:text-foreground flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full px-2.5 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none sm:px-3 sm:text-sm"
                        aria-label={`${t('a11y.languageSelector')}: ${currentLocaleName}`}
                    >
                        <Globe className="mr-1.5 h-4 w-4" aria-hidden="true" />
                        <span>{locale.toUpperCase()}</span>
                    </button>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align={variant === 'labelled' ? 'start' : 'end'}
            >
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
                                <span>
                                    <span className="text-muted-foreground mr-2 font-medium">
                                        {loc.code.toUpperCase()}
                                    </span>
                                    {loc.native}
                                </span>
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
