import type { PropsWithChildren } from 'react';

/**
 * Centered auth column. The brand link lives in the header (`BrandLogo`
 * from the site settings); each page renders its own `<h1>` so it is part
 * of the SSR HTML.
 */
export default function AuthSimpleLayout({ children }: PropsWithChildren) {
    return (
        <main
            id="main-content"
            className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
        >
            <div className="flex w-full max-w-sm flex-col gap-8">
                {children}
            </div>
        </main>
    );
}
