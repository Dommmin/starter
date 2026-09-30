import { useRef } from 'react';

/**
 * Focus handlers for a controlled Radix dialog opened without its own
 * `Dialog.Trigger` (from a row action, a shortcut or a toolbar button).
 * Radix returns focus only to `Dialog.Trigger`; these handlers remember the
 * element that was focused when the dialog opened and focus it again on
 * close, as long as it is still in the document.
 */
export function useReturnFocus() {
    const returnTarget = useRef<HTMLElement | null>(null);

    return {
        onOpenAutoFocus: () => {
            returnTarget.current =
                document.activeElement instanceof HTMLElement &&
                document.activeElement !== document.body
                    ? document.activeElement
                    : null;
        },
        onCloseAutoFocus: (event: Event) => {
            const target = returnTarget.current;
            returnTarget.current = null;

            if (target?.isConnected) {
                event.preventDefault();
                target.focus();
            }
        },
    };
}
