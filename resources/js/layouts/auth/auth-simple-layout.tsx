import type { PropsWithChildren } from 'react';
import { CenteredLayout } from '@/design-system/primitives';

/**
 * Centered auth column. The brand link lives in the header (`BrandLogo`
 * from the site settings); each page renders its own `<h1>` so it is part
 * of the SSR HTML.
 */
export default function AuthSimpleLayout({ children }: PropsWithChildren) {
    return <CenteredLayout>{children}</CenteredLayout>;
}
