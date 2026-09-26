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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
