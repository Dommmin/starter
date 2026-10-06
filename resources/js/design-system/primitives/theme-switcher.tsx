import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/i18n';
import {
    lazyPopupTriggerProps,
    useLazyMenuState,
    useLazyModule,
    type LazyPopupTriggerProps,
} from './lazy-popup';
import { themeModes } from './theme-modes';

export type ThemeSwitcherProps = {
    /**
     * `icon` (default): compact icon button named by `aria-label`.
     * `labelled`: full-width row with the icon and a visible
     * „Theme: System” caption that is the button's accessible name.
     * `inline`: the same caption as a compact muted control, e.g. in the
     * footer.
     */
    variant?: 'icon' | 'labelled' | 'inline';
    className?: never;
    style?: never;
};

const captionClasses: Record<'labelled' | 'inline', string> = {
    labelled:
        'focus-visible:ring-ring text-foreground hover:bg-surface-subtle inline-flex min-h-[44px] w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
    inline: 'focus-visible:ring-ring text-muted-foreground hover:bg-surface-subtle hover:text-foreground -mx-3 inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-full px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
};

const loadThemeMenu = () => import('./theme-menu');

/**
 * Theme dropdown. The Radix menu loads on the first opening (see
 * `lazy-popup.ts`); until then the trigger renders the same closed markup.
 */
export function ThemeSwitcher({ variant = 'icon' }: ThemeSwitcherProps) {
    const { appearance } = useAppearance();
    const { t } = useTranslation();
    const menu = useLazyMenuState();
    const { module, preload } = useLazyModule(loadThemeMenu, menu.open);

    const currentMode =
        themeModes.find(({ value }) => value === appearance) ?? themeModes[2];
    const ActiveIcon = currentMode.icon;
    const currentLabel = t(currentMode.labelKey);
    const triggerLabel = `${t('a11y.themeSwitcher')}: ${currentLabel}`;

    const renderTrigger = (props?: LazyPopupTriggerProps) =>
        variant === 'icon' ? (
            <button
                type="button"
                aria-label={triggerLabel}
                title={triggerLabel}
                className="focus-visible:ring-ring text-text-subtle hover:bg-surface-subtle hover:text-foreground inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full p-2 transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                {...props}
            >
                <ActiveIcon className="h-4 w-4 shrink-0" aria-hidden="true" />
            </button>
        ) : (
            <button
                type="button"
                className={captionClasses[variant]}
                {...props}
            >
                <ActiveIcon className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{`${t('theme.label')}: ${currentLabel}`}</span>
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

    const ThemeMenu = module.default;

    return (
        <ThemeMenu
            trigger={renderTrigger()}
            align={variant === 'labelled' ? 'start' : 'end'}
            open={menu.open}
            openedWithKeyboard={menu.openedWithKeyboard}
            onOpenChange={menu.setOpen}
        />
    );
}
