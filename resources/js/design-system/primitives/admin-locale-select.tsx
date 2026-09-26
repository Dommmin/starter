import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import AdminLocaleController from '@/actions/App/Http/Controllers/Settings/AdminLocaleController';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/i18n';

export type AdminLocaleSelectProps = {
    className?: never;
    style?: never;
};

export function AdminLocaleSelect() {
    const { t, locale, availableLocales } = useTranslation();
    const [isPending, setIsPending] = useState(false);

    if (availableLocales.length <= 1) {
        return null;
    }

    const handleChange = (value: string) => {
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
                    toast.success(t('admin.languageUpdated'));
                },
                onError: () => {
                    toast.error(t('errors.serverError.description'));
                },
                onFinish: () => {
                    setIsPending(false);
                },
            },
        );
    };

    return (
        <Select
            value={locale}
            onValueChange={handleChange}
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
