import {
    useEffect,
    useRef,
    useState,
    type FocusEvent,
    type KeyboardEvent,
    type PointerEvent,
} from 'react';

export type LazyPopupKind = 'menu' | 'dialog';

export type LazyPopupTriggerProps = {
    'aria-haspopup': LazyPopupKind;
    'aria-expanded': false;
    'data-state': 'closed';
    'data-slot'?: 'dropdown-menu-trigger';
    onPointerEnter: () => void;
    onFocus: () => void;
    onPointerDown?: (event: PointerEvent<HTMLButtonElement>) => void;
    onKeyDown?: (event: KeyboardEvent<HTMLButtonElement>) => void;
    onClick: () => void;
};

/**
 * Loads the module of a popup (Radix menu or dialog) only once it is first
 * needed, so public pages do not download it with their first render.
 * `preload` warms the import on hover or focus of the trigger.
 */
export function useLazyModule<Module>(
    load: () => Promise<Module>,
    needed: boolean,
): { module: Module | null; preload: () => void } {
    const [loaded, setLoaded] = useState<{ module: Module } | null>(null);

    useEffect(() => {
        if (!needed || loaded) {
            return;
        }

        let cancelled = false;

        void load().then((module) => {
            if (!cancelled) {
                setLoaded({ module });
            }
        });

        return () => {
            cancelled = true;
        };
    }, [load, needed, loaded]);

    return {
        module: loaded?.module ?? null,
        preload: () => {
            void load();
        },
    };
}

/**
 * Props of a closed trigger rendered until its popup module is loaded. They
 * repeat the ARIA state of the Radix trigger (the SSR markup stays the same)
 * and open on the same input: a menu on primary pointer down or
 * Enter/Space/ArrowDown, a dialog on click. Opening only sets the state; the
 * Radix component mounts open once its module arrives and takes over focus,
 * Escape and focus return.
 */
export function lazyPopupTriggerProps(
    kind: LazyPopupKind,
    {
        open,
        preload,
    }: { open: (withKeyboard: boolean) => void; preload: () => void },
): LazyPopupTriggerProps {
    if (kind === 'dialog') {
        return {
            'aria-haspopup': 'dialog',
            'aria-expanded': false,
            'data-state': 'closed',
            onPointerEnter: preload,
            onFocus: preload,
            onClick: () => open(false),
        };
    }

    return {
        'aria-haspopup': 'menu',
        'aria-expanded': false,
        'data-state': 'closed',
        'data-slot': 'dropdown-menu-trigger',
        onPointerEnter: preload,
        onFocus: preload,
        onPointerDown: (event) => {
            if (event.button === 0 && !event.ctrlKey) {
                event.preventDefault();
                open(false);
            }
        },
        onKeyDown: (event) => {
            if (['Enter', ' ', 'ArrowDown'].includes(event.key)) {
                event.preventDefault();
                open(true);
            }
        },
        // Assistive technology may activate the button with a bare click.
        onClick: () => open(false),
    };
}

/**
 * `onFocus` of the content of a lazily loaded menu. Radix focuses the first
 * item only for a keydown it saw itself, which happened before the menu
 * mounted; when the first opening came from the keyboard, focus moves on
 * from the content to its first item once.
 */
export function useFocusFirstMenuItemOnce(
    openedWithKeyboard: boolean,
): (event: FocusEvent<HTMLElement>) => void {
    const pending = useRef(openedWithKeyboard);

    return (event) => {
        if (!pending.current || event.target !== event.currentTarget) {
            return;
        }

        pending.current = false;
        event.currentTarget
            .querySelector<HTMLElement>(
                '[role^="menuitem"]:not([data-disabled])',
            )
            ?.focus({ preventScroll: true });
    };
}

/** Open state of a lazily loaded menu, opened before its module arrives. */
export function useLazyMenuState(): {
    open: boolean;
    openedWithKeyboard: boolean;
    setOpen: (open: boolean) => void;
    openFromTrigger: (withKeyboard: boolean) => void;
} {
    const [state, setState] = useState({
        open: false,
        openedWithKeyboard: false,
    });

    return {
        ...state,
        setOpen: (open) => setState({ open, openedWithKeyboard: false }),
        openFromTrigger: (withKeyboard) =>
            setState({ open: true, openedWithKeyboard: withKeyboard }),
    };
}
