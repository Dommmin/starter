import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import React, {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { createTranslator } from './translator';
import type {
    I18nPayload,
    TranslationFunction,
    UseTranslationReturn,
} from './types';

export * from './translator';
export * from './types';

interface I18nContextValue {
    payload: I18nPayload;
    t: TranslationFunction;
}

const I18nContext = createContext<I18nContextValue | null>(null);

/**
 * Wraps the Inertia app via `withApp` (see createInertiaApp in app.tsx), so it
 * receives the initial page synchronously from setup — usePage() is not
 * available here because this component renders outside the Inertia page
 * context that <App> establishes around its children.
 *
 * The translator is a pure function of the shared `i18n` payload: it is
 * rebuilt whenever navigation brings a new payload, so SSR and the first
 * client render produce the same strings.
 */
export function I18nProvider({
    initialPage,
    children,
}: {
    initialPage: Page<PageProps & SharedPageProps>;
    children: React.ReactNode;
}) {
    const [payload, setPayload] = useState<I18nPayload>(
        () => initialPage.props.i18n,
    );

    useEffect(() => {
        const updatePayload = (page?: Page<PageProps & SharedPageProps>) => {
            const nextI18n = page?.props?.i18n as I18nPayload | undefined;
            if (nextI18n) {
                setPayload(nextI18n);
            }
        };

        const unregisterNavigate = router.on('navigate', (event) => {
            updatePayload(event.detail.page);
        });

        const unregisterSuccess = router.on('success', (event) => {
            updatePayload(event.detail.page);
        });

        return () => {
            unregisterNavigate();
            unregisterSuccess();
        };
    }, []);

    const { locale, dir } = payload;

    const value = useMemo<I18nContextValue>(
        () => ({
            payload,
            t: createTranslator(payload.locale, payload.messages),
        }),
        [payload],
    );

    useEffect(() => {
        if (typeof document !== 'undefined') {
            if (document.documentElement.lang !== locale) {
                document.documentElement.lang = locale;
            }
            if (document.documentElement.dir !== dir) {
                document.documentElement.dir = dir;
            }
        }
    }, [locale, dir]);

    return (
        <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
    );
}

export function useTranslation(): UseTranslationReturn {
    const context = useContext(I18nContext);

    if (!context) {
        throw new Error(
            'useTranslation() musi być wywołane wewnątrz <I18nProvider>. Sprawdź, czy I18nProvider opakowuje aplikację w app.tsx.',
        );
    }

    const { payload, t } = context;

    const formatNumber = useMemo(() => {
        return (value: number, options?: Intl.NumberFormatOptions): string => {
            try {
                return new Intl.NumberFormat(payload.locale, options).format(
                    value,
                );
            } catch {
                return String(value);
            }
        };
    }, [payload.locale]);

    const formatDate = useMemo(() => {
        return (
            date: Date | string | number,
            options?: Intl.DateTimeFormatOptions,
        ): string => {
            try {
                const d = typeof date === 'object' ? date : new Date(date);
                return new Intl.DateTimeFormat(payload.locale, options).format(
                    d,
                );
            } catch {
                return String(date);
            }
        };
    }, [payload.locale]);

    return {
        t,
        locale: payload.locale,
        defaultLocale: payload.defaultLocale,
        fallback: payload.fallback,
        dir: payload.dir,
        availableLocales: payload.availableLocales,
        area: payload.area,
        formatNumber,
        formatDate,
    };
}
