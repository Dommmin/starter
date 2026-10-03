import {
    AdminLocaleSelect,
    Heading,
    Stack,
    Text,
    ThemeSwitcher,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function AppearanceToggleTab() {
    const { t } = useTranslation();

    return (
        <Stack gap="default">
            <ThemeSwitcher />

            <Stack gap="tight">
                <Heading level={2} variant="group">
                    {t('settings.appearance.language')}
                </Heading>
                <Text tone="muted">
                    {t('settings.appearance.languageSubheading')}
                </Text>
                <AdminLocaleSelect />
            </Stack>
        </Stack>
    );
}
