import type { ReactElement } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import type { User } from '@/types';
import { useFocusFirstMenuItemOnce } from './lazy-popup';
import { ThemeMenuGroup } from './theme-menu';

export type AccountMenuProps = {
    trigger: ReactElement;
    user: User;
    open: boolean;
    openedWithKeyboard: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Account dropdown of PublicHeader (theme choice, account links, logout),
 * loaded on the first opening (see `lazy-popup.ts`).
 */
export default function AccountMenu({
    trigger,
    user,
    open,
    openedWithKeyboard,
    onOpenChange,
}: AccountMenuProps) {
    const focusFirstItemOnce = useFocusFirstMenuItemOnce(openedWithKeyboard);

    return (
        <DropdownMenu open={open} onOpenChange={onOpenChange}>
            <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-56"
                align="end"
                onFocus={focusFirstItemOnce}
            >
                <ThemeMenuGroup />
                <DropdownMenuSeparator />
                <UserMenuContent user={user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
