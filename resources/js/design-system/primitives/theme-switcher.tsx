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

const modes: { value: Appearance; icon: typeof Sun; labelKey: string }[] = [
    { value: 'light', icon: Sun, labelKey: 'theme.light' },
    { value: 'dark', icon: Moon, labelKey: 'theme.dark' },
    { value: 'system', icon: Monitor, labelKey: 'theme.system' },
];

const captionClasses: Record<'labelled' | 'inline', string> = {
    labelled:
        'focus-visible:ring-ring text-foreground hover:bg-surface-subtle inline-flex min-h-[44px] w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
    inline: 'focus-visible:ring-ring text-muted-foreground hover:bg-surface-subtle hover:text-foreground -mx-3 inline-flex min-h-[44px] cursor-pointer items-center gap-2 rounded-full px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
};

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

export function ThemeSwitcher({ variant = 'icon' }: ThemeSwitcherProps) {
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
                {variant === 'icon' ? (
                    <button
                        type="button"
                        aria-label={triggerLabel}
                        title={triggerLabel}
                        className="focus-visible:ring-ring text-text-subtle hover:bg-surface-subtle hover:text-foreground inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full p-2 transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <ActiveIcon
                            className="h-4 w-4 shrink-0"
                            aria-hidden="true"
                        />
                    </button>
                ) : (
                    <button type="button" className={captionClasses[variant]}>
                        <ActiveIcon
                            className="h-4 w-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{`${t('theme.label')}: ${currentLabel}`}</span>
                    </button>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align={variant === 'labelled' ? 'start' : 'end'}
                className="w-36"
            >
                <ThemeRadioItems />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
