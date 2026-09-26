import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { createInstance, type i18n as I18nInstance } from 'i18next';
import React, {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import {
    I18nextProvider,
    initReactI18next,
    useTranslation as useI18nextTranslation,
} from 'react-i18next';
import { transformLaravelMessagesToI18next } from './adapter';
import type {
    I18nPayload,
    TranslationFunction,
    TranslationParams,
    UseTranslationReturn,
} from './types';

export * from './adapter';
export * from './types';

const I18nContext = createContext<I18nPayload | null>(null);

export function createI18nInstance(
    locale: string,
    messages: Record<string, unknown>,
    fallback = 'en',
): I18nInstance {
    const instance = createInstance();
    const transformed = transformLaravelMessagesToI18next(messages);

    void instance.use(initReactI18next).init({
        lng: locale,
        fallbackLng: fallback,
        resources: {
            [locale]: {
                translation: transformed,
            },
            ...(fallback !== locale
                ? {
                      [fallback]: {
                          translation: transformed,
                      },
                  }
                : {}),
        },
        interpolation: {
            escapeValue: false,
        },
        returnNull: false,
        returnEmptyString: false,
        keySeparator: '.',
        nsSeparator: false,
    });

    return instance;
}

/**
 * Wraps the Inertia app via `withApp` (see createInertiaApp in app.tsx), so it
 * receives the initial page synchronously from setup — usePage() is not
 * available here because this component renders outside the Inertia page
 * context that <App> establishes around its children.
 *
 * The i18next instance is created once per provider mount (once per request
 * on SSR, once per browser session on the client) and updated in place on
 * navigation via addResourceBundle/changeLanguage, rather than replaced with
 * a new instance. react-i18next's useTranslation() caches its snapshot by
 * language + revision, not by instance identity — swapping in a new instance
 * while the language stays the same (e.g. two 'de' pages in a row) would
 * leave it serving a stale, cached translator bound to the old instance.
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

    const [instance] = useState<I18nInstance>(() =>
        createI18nInstance(payload.locale, payload.messages, payload.fallback),
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

    const { locale, messages, fallback, dir } = payload;

    useEffect(() => {
        const transformed = transformLaravelMessagesToI18next(messages);
        instance.addResourceBundle(
            locale,
            'translation',
            transformed,
            true,
            true,
        );
        if (fallback !== locale) {
            instance.addResourceBundle(
                fallback,
                'translation',
                transformed,
                true,
                true,
            );
        }
        // Always re-run changeLanguage, even when the locale string is
        // unchanged: it's what emits 'languageChanged', which is what tells
        // react-i18next's useTranslation() to drop its cached translator and
        // pick up the resource bundle we just replaced above (see the
        // I18nProvider doc comment).
        void instance.changeLanguage(locale);
    }, [instance, locale, messages, fallback]);

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
        <I18nContext.Provider value={payload}>
            <I18nextProvider i18n={instance}>{children}</I18nextProvider>
        </I18nContext.Provider>
    );
}

export function useTranslation(): UseTranslationReturn {
    const payload = useContext(I18nContext);

    if (!payload) {
        throw new Error(
            'useTranslation() musi być wywołane wewnątrz <I18nProvider>. Sprawdź, czy I18nProvider opakowuje aplikację w app.tsx.',
        );
    }

    const { t: i18nT } = useI18nextTranslation();

    const t: TranslationFunction = useMemo(() => {
        return (
            key: string,
            params?: TranslationParams,
            count?: number,
        ): string => {
            let options: Record<string, unknown> | undefined = undefined;
            if (params || count !== undefined) {
                options = {
                    ...params,
                    ...(count !== undefined ? { count } : {}),
                };
            }

            const result = options ? i18nT(key, options) : i18nT(key);
            return typeof result === 'string' ? result : key;
        };
    }, [i18nT]);

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
