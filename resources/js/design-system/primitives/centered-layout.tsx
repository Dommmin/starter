import type { ReactNode } from 'react';

export type CenteredLayoutProps = {
    children: ReactNode;
    className?: never;
    style?: never;
};

/**
 * Full-height `<main id="main-content">` with a single narrow column centred
 * on the page (sign-in, password reset and other focused tasks). Each page
 * renders its own `<h1>` inside so it is part of the SSR HTML.
 */
export function CenteredLayout({ children }: CenteredLayoutProps) {
    return (
        <main
            id="main-content"
            className="bg-background flex min-h-svh flex-col items-center justify-center p-6 md:p-10"
        >
            <div className="flex w-full max-w-sm flex-col gap-8">
                {children}
            </div>
        </main>
    );
}
