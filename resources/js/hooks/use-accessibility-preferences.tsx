import { useSyncExternalStore } from 'react';

export type TextSize = 'default' | 'large' | 'larger';

/**
 * Visitor display preferences of the public site. Each one is rendered on
 * <html> as a data attribute (`data-text-size`, `data-contrast`,
 * `data-motion`, `data-links`) that the tokens in `app.css` react to; the
 * default value renders no attribute, so the operating system settings
 * (`prefers-contrast`, `prefers-reduced-motion`) keep applying.
 */
export type AccessibilityPreferences = {
    readonly textSize: TextSize;
    readonly highContrast: boolean;
    readonly reduceMotion: boolean;
    readonly underlineLinks: boolean;
};

export type UseAccessibilityPreferencesReturn = {
    readonly preferences: AccessibilityPreferences;
    readonly updatePreferences: (
        changes: Partial<AccessibilityPreferences>,
    ) => void;
    readonly resetPreferences: () => void;
};

/** localStorage key; keep in sync with the inline script of app.blade.php. */
export const ACCESSIBILITY_STORAGE_KEY = 'a11y-preferences';

export const DEFAULT_ACCESSIBILITY_PREFERENCES: AccessibilityPreferences = {
    textSize: 'default',
    highContrast: false,
    reduceMotion: false,
    underlineLinks: false,
};

const listeners = new Set<() => void>();
let currentPreferences: AccessibilityPreferences =
    DEFAULT_ACCESSIBILITY_PREFERENCES;

const isTextSize = (value: unknown): value is TextSize =>
    value === 'default' || value === 'large' || value === 'larger';

/**
 * Reads stored preferences, dropping unknown or malformed values.
 */
export function parseAccessibilityPreferences(
    raw: string | null,
): AccessibilityPreferences {
    if (raw === null) {
        return DEFAULT_ACCESSIBILITY_PREFERENCES;
    }

    let stored: unknown;

    try {
        stored = JSON.parse(raw);
    } catch {
        return DEFAULT_ACCESSIBILITY_PREFERENCES;
    }

    if (typeof stored !== 'object' || stored === null) {
        return DEFAULT_ACCESSIBILITY_PREFERENCES;
    }

    const values = stored as Record<string, unknown>;

    return {
        textSize: isTextSize(values.textSize) ? values.textSize : 'default',
        highContrast: values.highContrast === true,
        reduceMotion: values.reduceMotion === true,
        underlineLinks: values.underlineLinks === true,
    };
}

const isDefault = (preferences: AccessibilityPreferences): boolean =>
    preferences.textSize === 'default' &&
    !preferences.highContrast &&
    !preferences.reduceMotion &&
    !preferences.underlineLinks;

const setAttribute = (name: string, value: string | null): void => {
    if (value === null) {
        document.documentElement.removeAttribute(name);
    } else {
        document.documentElement.setAttribute(name, value);
    }
};

const applyPreferences = (preferences: AccessibilityPreferences): void => {
    if (typeof document === 'undefined') {
        return;
    }

    setAttribute(
        'data-text-size',
        preferences.textSize === 'default' ? null : preferences.textSize,
    );
    setAttribute('data-contrast', preferences.highContrast ? 'more' : null);
    setAttribute('data-motion', preferences.reduceMotion ? 'reduce' : null);
    setAttribute('data-links', preferences.underlineLinks ? 'underline' : null);
};

const readStored = (): AccessibilityPreferences => {
    try {
        return parseAccessibilityPreferences(
            localStorage.getItem(ACCESSIBILITY_STORAGE_KEY),
        );
    } catch {
        return DEFAULT_ACCESSIBILITY_PREFERENCES;
    }
};

const store = (preferences: AccessibilityPreferences): void => {
    try {
        if (isDefault(preferences)) {
            localStorage.removeItem(ACCESSIBILITY_STORAGE_KEY);
        } else {
            localStorage.setItem(
                ACCESSIBILITY_STORAGE_KEY,
                JSON.stringify(preferences),
            );
        }
    } catch {
        // Storage may be blocked (private mode); the choice still applies
        // to the current page.
    }
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const setPreferences = (preferences: AccessibilityPreferences): void => {
    currentPreferences = preferences;
    store(preferences);
    applyPreferences(preferences);
    listeners.forEach((listener) => listener());
};

/**
 * Applies the stored preferences on load. The inline script of
 * app.blade.php already set the attributes before the first paint; this
 * syncs the store with them.
 */
export function initializeAccessibilityPreferences(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentPreferences = readStored();
    applyPreferences(currentPreferences);
}

export function useAccessibilityPreferences(): UseAccessibilityPreferencesReturn {
    const preferences = useSyncExternalStore(
        subscribe,
        () => currentPreferences,
        () => DEFAULT_ACCESSIBILITY_PREFERENCES,
    );

    return {
        preferences,
        updatePreferences: (changes) =>
            setPreferences({ ...currentPreferences, ...changes }),
        resetPreferences: () =>
            setPreferences(DEFAULT_ACCESSIBILITY_PREFERENCES),
    } as const;
}
