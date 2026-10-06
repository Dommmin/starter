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
    /** Shows the current mode next to the icon (e.g. in the footer). */
    withLabel?: boolean;
    className?: never;
    style?: never;
};

const loadThemeMenu = () => import('./theme-menu');

export function ThemeSwitcher({ withLabel = false }: ThemeSwitcherProps) {
    const { appearance } = useAppearance();
    const { t } = useTranslation();
    const menu = useLazyMenuState();
    const { module, preload } = useLazyModule(loadThemeMenu, menu.open);

    const currentMode =
        themeModes.find(({ value }) => value === appearance) ?? themeModes[2];
    const ActiveIcon = currentMode.icon;
    const currentLabel = t(currentMode.labelKey);
    const triggerLabel = `${t('a11y.themeSwitcher')}: ${currentLabel}`;

    const renderTrigger = (props?: LazyPopupTriggerProps) => (
        <button
            type="button"
            aria-label={triggerLabel}
            title={triggerLabel}
            className={
                withLabel
                    ? 'focus-visible:ring-ring text-muted-foreground hover:bg-surface-subtle hover:text-foreground -mx-3 inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-full px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none'
                    : 'focus-visible:ring-ring text-text-subtle hover:bg-surface-subtle hover:text-foreground inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full p-2 transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none'
            }
            {...props}
        >
            <ActiveIcon className="h-4 w-4 shrink-0" aria-hidden="true" />
            {withLabel && (
                <span aria-hidden="true">
                    {t('theme.label')}: {currentLabel}
                </span>
            )}
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
            open={menu.open}
            openedWithKeyboard={menu.openedWithKeyboard}
            onOpenChange={menu.setOpen}
        />
    );
}
