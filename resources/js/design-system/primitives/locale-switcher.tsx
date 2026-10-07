import { usePage } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import { useTranslation } from '@/i18n';
import { home } from '@/routes';
import { home as localizedHome } from '@/routes/localized';
import {
    lazyPopupTriggerProps,
    useLazyMenuState,
    useLazyModule,
    type LazyPopupTriggerProps,
} from './lazy-popup';

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

const loadLocaleMenu = () => import('./locale-menu');

/**
 * Language dropdown. The Radix menu loads on the first opening (see
 * `lazy-popup.ts`); until then the trigger renders the same closed markup.
 */
export function LocaleSwitcher({ variant = 'icon' }: LocaleSwitcherProps) {
    const { t, locale, defaultLocale, availableLocales } = useTranslation();
    const page = usePage();
    const menu = useLazyMenuState();
    const { module, preload } = useLazyModule(loadLocaleMenu, menu.open);
    const alternateUrls =
        (page.props as { i18n?: { alternateUrls?: Record<string, string> } })
            .i18n?.alternateUrls ?? {};

    if (availableLocales.length <= 1) {
        return null;
    }

    const currentLocale = availableLocales.find((l) => l.code === locale);
    const currentLocaleName = currentLocale?.native ?? locale.toUpperCase();

    const renderTrigger = (props?: LazyPopupTriggerProps) =>
        variant === 'labelled' ? (
            <button
                type="button"
                className="focus-visible:ring-ring text-foreground hover:bg-surface-subtle inline-flex min-h-[44px] w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                {...props}
            >
                <Globe className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{`${t('language.label')}: ${currentLocaleName}`}</span>
            </button>
        ) : (
            <button
                type="button"
                className="hover:bg-surface-subtle focus-visible:ring-ring text-text-subtle hover:text-foreground flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full px-2.5 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none sm:px-3 sm:text-sm"
                aria-label={`${t('a11y.languageSelector')}: ${currentLocaleName}`}
                {...props}
            >
                <Globe className="mr-1.5 h-4 w-4" aria-hidden="true" />
                <span>{locale.toUpperCase()}</span>
            </button>
        );

    if (!module) {
        return renderTrigger(
            lazyPopupTriggerProps('menu', {
                open: menu.openFromTrigger,
                preload,
            }),
        );
    }

    const LocaleMenu = module.default;

    return (
        <LocaleMenu
            trigger={renderTrigger()}
            align={variant === 'labelled' ? 'start' : 'end'}
            open={menu.open}
            openedWithKeyboard={menu.openedWithKeyboard}
            onOpenChange={menu.setOpen}
            options={availableLocales.map((option) => ({
                code: option.code,
                native: option.native,
                href:
                    alternateUrls[option.code] ??
                    (option.code === defaultLocale
                        ? home.url()
                        : localizedHome.url({ locale: option.code })),
                isCurrent: option.code === locale,
            }))}
        />
    );
}
