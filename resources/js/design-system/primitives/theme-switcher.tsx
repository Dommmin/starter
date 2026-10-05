import { Monitor, Moon, Sun } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/i18n';

export type ThemeSwitcherProps = {
    /** Shows the current mode next to the icon (e.g. in the footer). */
    withLabel?: boolean;
    className?: never;
    style?: never;
};

const modes: { value: Appearance; icon: typeof Sun; labelKey: string }[] = [
    { value: 'light', icon: Sun, labelKey: 'theme.light' },
    { value: 'dark', icon: Moon, labelKey: 'theme.dark' },
    { value: 'system', icon: Monitor, labelKey: 'theme.system' },
];

function ThemeRadioItems() {
    const { appearance, updateAppearance } = useAppearance();
    const { t } = useTranslation();

    return (
        <DropdownMenuRadioGroup
            value={appearance}
            onValueChange={(val) => updateAppearance(val as Appearance)}
        >
            {modes.map(({ value, icon: ModeIcon, labelKey }) => (
                <DropdownMenuRadioItem
                    key={value}
                    value={value}
                    className="cursor-pointer"
                >
                    <ModeIcon className="mr-2 h-4 w-4" aria-hidden="true" />
                    <span>{t(labelKey)}</span>
                </DropdownMenuRadioItem>
            ))}
        </DropdownMenuRadioGroup>
    );
}

/**
 * Theme choice as a labelled radio group for an existing dropdown menu, e.g.
 * the account menu of the public header.
 */
export function ThemeMenuGroup() {
    const { t } = useTranslation();

    return (
        <>
            <DropdownMenuLabel className="text-muted-foreground px-2 py-1.5 text-xs font-medium">
                {t('theme.label')}
            </DropdownMenuLabel>
            <ThemeRadioItems />
        </>
    );
}

export function ThemeSwitcher({ withLabel = false }: ThemeSwitcherProps) {
    const { appearance } = useAppearance();
    const { t } = useTranslation();

    const currentMode =
        modes.find(({ value }) => value === appearance) ?? modes[2];
    const ActiveIcon = currentMode.icon;
    const currentLabel = t(currentMode.labelKey);
    const triggerLabel = `${t('a11y.themeSwitcher')}: ${currentLabel}`;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={triggerLabel}
                    title={triggerLabel}
                    className={
                        withLabel
                            ? 'focus-visible:ring-ring text-muted-foreground hover:bg-surface-subtle hover:text-foreground -mx-3 inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-full px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none'
                            : 'focus-visible:ring-ring text-text-subtle hover:bg-surface-subtle hover:text-foreground inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full p-2 transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none'
                    }
                >
                    <ActiveIcon
                        className="h-4 w-4 shrink-0"
                        aria-hidden="true"
                    />
                    {withLabel && (
                        <span aria-hidden="true">
                            {t('theme.label')}: {currentLabel}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-36">
                <ThemeRadioItems />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
