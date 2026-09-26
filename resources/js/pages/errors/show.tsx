import { ServerCrash } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Button,
    EmptyState,
    Footer,
    Heading,
    Maintenance,
    NotFound,
    PermissionDenied,
    PublicHeader,
    Section,
    Seo,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { home } from '@/routes';
import { home as localizedHome } from '@/routes/localized';

type ErrorKey = 'forbidden' | 'notFound' | 'serverError' | 'serviceUnavailable';

const errorKeys: Record<App.Data.Errors.ErrorPageData['status'], ErrorKey> = {
    403: 'forbidden',
    404: 'notFound',
    500: 'serverError',
    503: 'serviceUnavailable',
};

export default function ErrorShow({ status }: App.Data.Errors.ErrorPageData) {
    const { t, locale, defaultLocale, area } = useTranslation();
    const key = errorKeys[status];
    const description = t(`errors.${key}.description`);
    const homeUrl =
        area === 'public' && locale !== defaultLocale
            ? localizedHome.url({ locale })
            : home.url();

    const action = (
        <Button variant="outline" href={homeUrl}>
            <span>{t('errors.notFound.backHome')}</span>
        </Button>
    );

    const states: Record<ErrorKey, ReactNode> = {
        forbidden: <PermissionDenied title={description} action={action} />,
        notFound: <NotFound title={description} action={action} />,
        serverError: (
            <EmptyState
                icon={ServerCrash}
                title={description}
                action={action}
            />
        ),
        serviceUnavailable: <Maintenance title={description} />,
    };

    return (
        <>
            <Seo title={t(`errors.${key}.title`)} robots="noindex,nofollow" />

            <PublicHeader />

            <main id="main-content">
                <Section spacing="relaxed" container="reading">
                    <Stack gap="tight" align="center">
                        <Heading level={1} variant="page" align="center">
                            {t(`errors.${key}.heading`)}
                        </Heading>
                        {states[key]}
                    </Stack>
                </Section>
            </main>

            <Footer
                copyright={`© ${new Date().getFullYear()} ${t('brand.name')}`}
            />
        </>
    );
}
