import type { ReactNode } from 'react';
import { PublicChrome, Section } from '@/design-system/primitives';

/**
 * Front-of-site frame for signed-in pages that belong to the public surface
 * (account settings): the same header and footer as public pages.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    return (
        <PublicChrome>
            <Section spacing="compact" container="wide">
                {children}
            </Section>
        </PublicChrome>
    );
}
