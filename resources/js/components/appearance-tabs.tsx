import Heading from '@/components/heading';
import {
    AdminLocaleSelect,
    Stack,
    ThemeSwitcher,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function AppearanceToggleTab() {
    const { t } = useTranslation();

    return (
        <Stack gap="default">
            <ThemeSwitcher />

            <Stack gap="tight">
                <Heading
                    variant="small"
                    title={t('settings.appearance.language')}
                    description={t('settings.appearance.languageSubheading')}
                />
                <AdminLocaleSelect />
            </Stack>
        </Stack>
    );
}
