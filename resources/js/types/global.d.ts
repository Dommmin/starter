import type { Auth } from '@/types/auth';

import type { I18nPayload } from '@/i18n/types';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: string;
            i18n: I18nPayload;
            seo: App.Data.Seo.SeoDefaultsData;
            site: App.Data.Settings.SiteSettingsData;
            sidebarOpen: boolean;
            /** Public area only (lazy shared prop). */
            navigation?: App.Data.Navigation.NavigationData;
            /** Public area only (lazy shared prop): cookie banner state. */
            consent?: App.Data.Consent.ConsentData;
            /** Admin area in the local environment only (design-system showcase). */
            designSystemUrl?: string;
            /** Present (true) only on the public demo. */
            demo?: boolean;
            [key: string]: unknown;
        };
    }
}
