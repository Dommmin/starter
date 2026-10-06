import type { PropsWithChildren } from 'react';
import {
    Container,
    Heading,
    SectionNav,
    SplitLayout,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/i18n';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    const items = [
        { id: 'profile', label: t('nav.profile'), href: edit() },
        { id: 'security', label: t('nav.security'), href: editSecurity() },
        {
            id: 'appearance',
            label: t('nav.appearance'),
            href: editAppearance(),
        },
    ].map((item) => ({
        ...item,
        current: isCurrentOrParentUrl(item.href),
        href: toUrl(item.href),
    }));

    return (
        <Stack gap="relaxed">
            <Stack gap="tight">
                <Heading level={2} variant="subsection">
                    {t('settings.title')}
                </Heading>
                <Text tone="muted">{t('settings.description')}</Text>
            </Stack>

            <SplitLayout
                ratio="sidebar"
                gap="relaxed"
                primary={
                    <SectionNav
                        items={items}
                        ariaLabel={t('a11y.accountSettings')}
                    />
                }
                secondary={
                    <Container width="reading" padding="none">
                        {children}
                    </Container>
                }
            />
        </Stack>
    );
}
