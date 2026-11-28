import { useState } from 'react';
import { useTranslation } from '@/i18n';
import { Button } from './button';
import { Link } from './link';

/**
 * A third-party booking calendar loaded only after the visitor asks for it,
 * so the provider gets no request and sets no cookies before that. A direct
 * link works without loading the frame.
 */
export function BookingEmbed({
    url,
    providerHost,
}: {
    url: string;
    providerHost: string;
}) {
    const { t } = useTranslation();
    const [isLoaded, setIsLoaded] = useState(false);

    return (
        <div className="flex flex-col gap-4">
            {isLoaded ? (
                <iframe
                    src={url}
                    title={t('booking.frameTitle')}
                    className="border-border-subtle bg-background h-[46rem] w-full rounded-lg border"
                    referrerPolicy="strict-origin-when-cross-origin"
                />
            ) : (
                <div className="border-border-subtle bg-surface-subtle flex flex-col items-start gap-3 rounded-lg border p-6">
                    <p className="text-foreground text-sm">
                        {t('booking.notice', { provider: providerHost })}
                    </p>
                    <Button onClick={() => setIsLoaded(true)}>
                        {t('booking.load')}
                    </Button>
                </div>
            )}
            <Link href={url} tone="muted" external>
                {t('booking.openDirectly', { provider: providerHost })}
            </Link>
        </div>
    );
}
