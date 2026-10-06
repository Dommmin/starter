import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { Heading, Stack, Text } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('settings.appearance.pageTitle')} />

            <Stack gap="default">
                <Stack gap="tight">
                    <Heading level={1} variant="group">
                        {t('settings.appearance.heading')}
                    </Heading>
                    <Text tone="muted">
                        {t('settings.appearance.subheading')}
                    </Text>
                </Stack>
                <AppearanceTabs />
            </Stack>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
