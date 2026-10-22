import type { ReactElement } from 'react';
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
import { useFocusFirstMenuItemOnce } from './lazy-popup';
import { themeModes } from './theme-modes';

function ThemeRadioItems() {
    const { appearance, updateAppearance } = useAppearance();
    const { t } = useTranslation();

    return (
        <DropdownMenuRadioGroup
            value={appearance}
            onValueChange={(val) => updateAppearance(val as Appearance)}
        >
            {themeModes.map(({ value, icon: ModeIcon, labelKey }) => (
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

export type ThemeMenuProps = {
    trigger: ReactElement;
    align: 'start' | 'end';
    open: boolean;
    openedWithKeyboard: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Radix dropdown of ThemeSwitcher, loaded on the first opening (see
 * `lazy-popup.ts`).
 */
export default function ThemeMenu({
    trigger,
    align,
    open,
    openedWithKeyboard,
    onOpenChange,
}: ThemeMenuProps) {
    const focusFirstItemOnce = useFocusFirstMenuItemOnce(openedWithKeyboard);

    return (
        <DropdownMenu open={open} onOpenChange={onOpenChange}>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent
                align={align}
                className="w-36"
                onFocus={focusFirstItemOnce}
            >
                <ThemeRadioItems />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
