import { router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLocaleController from '@/actions/App/Http/Controllers/Settings/AdminLocaleController';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/i18n';
import { notify } from './notify';

export type AdminLocaleSelectProps = {
    className?: never;
    style?: never;
};

/**
 * Persists the admin UI locale. Shared by AdminLocaleSelect (settings) and the
 * admin shell user menu so both report success and failure the same way.
 */
export function useAdminLocaleChange(): {
    isPending: boolean;
    changeLocale: (value: string) => void;
} {
    const { t, locale } = useTranslation();
    const [isPending, setIsPending] = useState(false);

    const changeLocale = (value: string) => {
        if (value === locale || isPending) {
            return;
        }

        setIsPending(true);
        router.patch(
            AdminLocaleController.update.url(),
            { locale: value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    notify({
                        tone: 'success',
                        message: t('admin.languageUpdated'),
                    });
                },
                onError: () => {
                    notify({
                        tone: 'danger',
                        message: t('errors.serverError.description'),
                    });
                },
                onFinish: () => {
                    setIsPending(false);
                },
            },
        );
    };

    return { isPending, changeLocale };
}

export function AdminLocaleSelect() {
    const { t, locale, availableLocales } = useTranslation();
    const { isPending, changeLocale } = useAdminLocaleChange();

    if (availableLocales.length <= 1) {
        return null;
    }

    return (
        <Select
            value={locale}
            onValueChange={changeLocale}
            disabled={isPending}
        >
            <SelectTrigger
                id="admin-locale"
                aria-label={t('settings.appearance.language')}
                className="w-full sm:w-64"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {availableLocales.map((loc) => (
                    <SelectItem key={loc.code} value={loc.code}>
                        {loc.native}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
