import { Menu } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { IconButton } from './icon-button';
import {
    lazyPopupTriggerProps,
    useLazyModule,
    type LazyPopupTriggerProps,
} from './lazy-popup';
import type { NavItem } from './nav-item';

export type MobileNavProps = {
    /** Visible panel heading, e.g. "Menu". */
    title: string;
    /** Entries with one level of `children`; `group` renders as a heading. */
    items: NavItem[];
    /** Translated accessible label for the trigger control. */
    openLabel: string;
    /** Translated accessible label for the close control. */
    closeLabel: string;
    /** Rendered at the bottom of the panel, e.g. login/register actions. */
    footer?: ReactNode;
    /**
     * Controls shown in the panel only below `sm` (e.g. theme and locale
     * switchers that the header shows inline from `sm` up).
     */
    utilities?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

const loadPanel = () => import('./mobile-nav-panel');

/**
 * Menu trigger with a slide-in navigation panel (Radix dialog). The panel
 * module loads on the first opening; until then the trigger renders the same
 * closed-dialog markup.
 */
export function MobileNav({
    title,
    items,
    openLabel,
    closeLabel,
    footer,
    utilities,
    open,
    onOpenChange,
}: MobileNavProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const isOpen = open ?? uncontrolledOpen;
    const { module, preload } = useLazyModule(loadPanel, isOpen);

    function setOpen(nextOpen: boolean) {
        if (open === undefined) {
            setUncontrolledOpen(nextOpen);
        }
        onOpenChange?.(nextOpen);
    }

    const renderTrigger = (props?: LazyPopupTriggerProps) => (
        <IconButton
            icon={Menu}
            ariaLabel={openLabel}
            variant="ghost"
            {...props}
        />
    );

    if (!module) {
        return renderTrigger(
            lazyPopupTriggerProps('dialog', {
                open: () => setOpen(true),
                preload,
            }),
        );
    }

    const MobileNavPanel = module.default;

    return (
        <MobileNavPanel
            trigger={renderTrigger()}
            title={title}
            items={items}
            closeLabel={closeLabel}
            footer={footer}
            utilities={utilities}
            open={isOpen}
            onOpenChange={setOpen}
        />
    );
}
