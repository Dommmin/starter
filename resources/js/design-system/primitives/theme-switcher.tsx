import { Monitor, Moon, Sun } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/i18n';

export type ThemeSwitcherProps = {
    className?: never;
    style?: never;
};

const modes: { value: Appearance; icon: typeof Sun; labelKey: string }[] = [
    { value: 'light', icon: Sun, labelKey: 'theme.light' },
    { value: 'dark', icon: Moon, labelKey: 'theme.dark' },
    { value: 'system', icon: Monitor, labelKey: 'theme.system' },
];

export function ThemeSwitcher() {
    const { appearance, updateAppearance } = useAppearance();
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
                    className="focus-visible:ring-ring text-text-subtle hover:bg-surface-subtle hover:text-foreground inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full p-2 transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <ActiveIcon
                        className="h-4 w-4 shrink-0"
                        aria-hidden="true"
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-36">
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
                            <ModeIcon
                                className="mr-2 h-4 w-4"
                                aria-hidden="true"
                            />
                            <span>{t(labelKey)}</span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
