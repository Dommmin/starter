import {
    BookingEmbed,
    Heading,
    PublicChrome,
    Section,
    Seo,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

export default function Booking({
    bookingUrl,
    providerHost,
}: App.Data.Booking.BookingPageData) {
    const { t } = useTranslation();

    return (
        <>
            <Seo title={t('booking.title')} />
            <PublicChrome>
                <Section spacing="default" container="reading">
                    <Stack gap="default">
                        <Heading level={1} variant="page">
                            {t('booking.title')}
                        </Heading>
                        <BookingEmbed
                            url={bookingUrl}
                            providerHost={providerHost}
                        />
                    </Stack>
                </Section>
            </PublicChrome>
        </>
    );
}
