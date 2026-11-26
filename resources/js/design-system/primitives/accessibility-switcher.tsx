import { Accessibility } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from '@/i18n';
import {
    lazyPopupTriggerProps,
    useLazyModule,
    type LazyPopupTriggerProps,
} from './lazy-popup';

export type AccessibilitySwitcherProps = {
    /**
     * `icon` (default): compact button with the accessibility symbol,
     * named by `aria-label`.
     * `labelled`: full-width row with the icon and a visible caption that
     * is the button's accessible name (e.g. in the mobile menu).
     */
    variant?: 'icon' | 'labelled';
    className?: never;
    style?: never;
};

const loadDialog = () => import('./accessibility-dialog');

const triggerClasses: Record<'icon' | 'labelled', string> = {
    icon: 'focus-visible:ring-ring text-text-subtle hover:bg-surface-subtle hover:text-foreground inline-flex min-h-[44px] min-w-[44px] cursor-pointer items-center justify-center rounded-full p-2 transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
    labelled:
        'focus-visible:ring-ring text-foreground hover:bg-surface-subtle inline-flex min-h-[44px] w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
};

/**
 * Opens the accessibility preferences of the public site (text size,
 * contrast, motion, link underlines). The dialog
 * (`accessibility-dialog.tsx`) loads on the first opening; until then the
 * trigger renders the same closed markup (see `lazy-popup.ts`).
 */
export function AccessibilitySwitcher({
    variant = 'icon',
}: AccessibilitySwitcherProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { module, preload } = useLazyModule(loadDialog, open);
    const label = t('accessibility.open');

    const renderTrigger = (props?: LazyPopupTriggerProps) => (
        <button
            type="button"
            aria-label={variant === 'icon' ? label : undefined}
            title={variant === 'icon' ? label : undefined}
            aria-haspopup="dialog"
            aria-expanded={open}
            className={triggerClasses[variant]}
            {...props}
            onClick={() => setOpen(true)}
        >
            <Accessibility className="h-4 w-4 shrink-0" aria-hidden="true" />
            {variant === 'labelled' && <span>{label}</span>}
        </button>
    );

    if (!module) {
        return renderTrigger(
            lazyPopupTriggerProps('dialog', {
                open: () => setOpen(true),
                preload,
            }),
        );
    }

    const AccessibilityDialog = module.default;

    return (
        <>
            {renderTrigger()}
            <AccessibilityDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
