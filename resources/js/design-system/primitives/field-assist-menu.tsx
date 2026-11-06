import type { ReactElement } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { FieldAssistMode } from './field-assist';
import { useFocusFirstMenuItemOnce } from './lazy-popup';

export type FieldAssistMenuProps = {
    trigger: ReactElement;
    modes: readonly FieldAssistMode[];
    open: boolean;
    openedWithKeyboard: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (mode: string) => void;
};

/**
 * Radix dropdown with the modes of a `FieldAssist`, loaded on the first
 * opening (see `lazy-popup.ts`), so public forms do not download it.
 */
export default function FieldAssistMenu({
    trigger,
    modes,
    open,
    openedWithKeyboard,
    onOpenChange,
    onSelect,
}: FieldAssistMenuProps) {
    const focusFirstItemOnce = useFocusFirstMenuItemOnce(openedWithKeyboard);

    return (
        <DropdownMenu open={open} onOpenChange={onOpenChange}>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent align="end" onFocus={focusFirstItemOnce}>
                {modes.map((mode) => (
                    <DropdownMenuItem
                        key={mode.value}
                        className="min-h-[44px] cursor-pointer"
                        onSelect={() => onSelect(mode.value)}
                    >
                        {mode.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
