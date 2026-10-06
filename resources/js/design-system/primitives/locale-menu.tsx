import type { ReactElement } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useFocusFirstMenuItemOnce } from './lazy-popup';

export type LocaleMenuOption = {
    code: string;
    native: string;
    href: string;
    isCurrent: boolean;
};

export type LocaleMenuProps = {
    trigger: ReactElement;
    options: LocaleMenuOption[];
    open: boolean;
    openedWithKeyboard: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Radix dropdown of LocaleSwitcher, loaded on the first opening (see
 * `lazy-popup.ts`), so public pages do not ship the menu with their first
 * render.
 */
export default function LocaleMenu({
    trigger,
    options,
    open,
    openedWithKeyboard,
    onOpenChange,
}: LocaleMenuProps) {
    const focusFirstItemOnce = useFocusFirstMenuItemOnce(openedWithKeyboard);

    return (
        <DropdownMenu open={open} onOpenChange={onOpenChange}>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent align="end" onFocus={focusFirstItemOnce}>
                {options.map((option) => (
                    <DropdownMenuItem key={option.code} asChild>
                        <a
                            href={option.href}
                            className="flex w-full items-center justify-between px-2.5 py-1.5 text-sm"
                        >
                            <span>
                                <span className="text-muted-foreground mr-2 font-medium">
                                    {option.code.toUpperCase()}
                                </span>
                                {option.native}
                            </span>
                            {option.isCurrent && (
                                <span className="text-primary ml-2 font-semibold">
                                    ✓
                                </span>
                            )}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
