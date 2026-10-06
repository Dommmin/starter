import { Head } from '@inertiajs/react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    Heading,
    PageHeader,
    Section,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { showcaseFamilies } from './sections';

/**
 * Local-only design-system showcase: every registered component family in
 * its states, for light/dark, RWD and keyboard review. Demo data is
 * synthetic; the route exists only with APP_ENV=local.
 */
export default function DesignSystemShowcase() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.designSystem.title')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.designSystem.title')}
                    description={t('admin.designSystem.description')}
                />

                <Card padding="compact">
                    <CardHeader>
                        <Heading level={2} variant="group">
                            {t('admin.designSystem.tocTitle')}
                        </Heading>
                    </CardHeader>
                    <CardContent>
                        <Stack gap="none" align="start">
                            {showcaseFamilies.map((family) => (
                                <Button
                                    key={family.id}
                                    variant="link"
                                    href={`#${family.id}`}
                                >
                                    {t(family.titleKey)}
                                </Button>
                            ))}
                        </Stack>
                    </CardContent>
                </Card>

                {showcaseFamilies.map(
                    ({ id, titleKey, descriptionKey, Component }) => (
                        <Section
                            key={id}
                            id={id}
                            ariaLabelledBy={`${id}-title`}
                            spacing="compact"
                            container="none"
                        >
                            <Stack gap="default">
                                <Stack gap="tight">
                                    <Heading
                                        id={`${id}-title`}
                                        level={2}
                                        variant="subsection"
                                    >
                                        {t(titleKey)}
                                    </Heading>
                                    <Text tone="muted">
                                        {t(descriptionKey)}
                                    </Text>
                                </Stack>
                                <Component />
                            </Stack>
                        </Section>
                    ),
                )}
            </Stack>
        </>
    );
}

DesignSystemShowcase.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
        {
            title: 'admin.designSystem.title',
            href: '/admin/design-system',
        },
    ],
};
