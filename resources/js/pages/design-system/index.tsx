import {
    Button,
    Card,
    CardContent,
    CardHeader,
    Heading,
    PublicChrome,
    Section,
    Seo,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { publicShowcaseFamilies } from './sections';
import { demoFooterGroups, demoNavItems } from './sections/demo-data';

/**
 * Local-only showcase of the public (WEB) components in the public frame
 * with SSR, for light/dark, RWD, keyboard and hydration review. The page
 * chrome itself is the header/footer demo (submenu, group, external link).
 * Demo data is synthetic; the route exists only with APP_ENV=local and is
 * never indexed nor listed in the sitemap.
 */
export default function PublicDesignSystemShowcase() {
    const { t } = useTranslation();

    return (
        <>
            <Seo
                title={t('admin.designSystem.web.title')}
                description={null}
                robots="noindex,nofollow"
            />

            <PublicChrome
                navItems={demoNavItems(t)}
                footer={{ groups: demoFooterGroups(t) }}
            >
                <Section spacing="compact" container="wide">
                    <Stack gap="relaxed">
                        <Stack gap="tight">
                            <Heading level={1} variant="page">
                                {t('admin.designSystem.web.title')}
                            </Heading>
                            <Text tone="muted">
                                {t('admin.designSystem.web.description')}
                            </Text>
                        </Stack>

                        <Card padding="compact">
                            <CardHeader>
                                <Heading level={2} variant="group">
                                    {t('admin.designSystem.tocTitle')}
                                </Heading>
                            </CardHeader>
                            <CardContent>
                                <Stack gap="none" align="start">
                                    {publicShowcaseFamilies.map((family) => (
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

                        {publicShowcaseFamilies.map(
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
                </Section>
            </PublicChrome>
        </>
    );
}
