import type { ReactNode } from 'react';
import { Footer, PublicHeader, Section } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

/**
 * Front-of-site frame for signed-in pages that belong to the public surface
 * (account settings): the same header and footer as public pages.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();

    return (
        <>
            <PublicHeader />

            <main id="main-content">
                <Section spacing="compact" container="wide">
                    {children}
                </Section>
            </main>

            <Footer
                copyright={`© ${new Date().getFullYear()} ${t('brand.name')}`}
            />
        </>
    );
}
