export interface LocaleMetadata {
    code: string;
    name: string;
    native: string;
    dir: 'ltr' | 'rtl';
}

export interface I18nPayload {
    area: 'public' | 'admin';
    locale: string;
    defaultLocale: string;
    fallback: string;
    dir: 'ltr' | 'rtl';
    availableLocales: LocaleMetadata[];
    messages: Record<string, unknown>;
}

export type TranslationParams = Record<string, string | number>;

export type TranslationFunction = (
    key: string,
    params?: TranslationParams,
    count?: number,
) => string;

export interface UseTranslationReturn {
    t: TranslationFunction;
    locale: string;
    defaultLocale: string;
    fallback: string;
    dir: 'ltr' | 'rtl';
    availableLocales: LocaleMetadata[];
    area: 'public' | 'admin';
    formatNumber: (value: number, options?: Intl.NumberFormatOptions) => string;
    formatDate: (
        date: Date | string | number,
        options?: Intl.DateTimeFormatOptions,
    ) => string;
}
