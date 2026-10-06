import type { ReactNode } from 'react';
import { HeaderUtility } from './header-utility';
import { Heading } from './heading';
import { Text } from './text';
import { ThemeSwitcher } from './theme-switcher';

export type AuthShellProps = {
    /** Page title, rendered as the only `h1`. */
    title: string;
    description?: string;
    /** The form of the auth page. */
    children: ReactNode;
    className?: never;
    style?: never;
};

/**
 * Frame of the sign-in, sign-up and recovery pages: the site header (brand
 * from the shared `site` settings, locale and theme switchers, since these
 * pages have no footer or account menu) and a narrow centred column in the
 * `main` landmark targeted by the skip link.
 */
export function AuthShell({ title, description, children }: AuthShellProps) {
    return (
        <div className="bg-background flex min-h-svh flex-col">
            <HeaderUtility>
                <ThemeSwitcher />
            </HeaderUtility>

            <main
                id="main-content"
                className="flex flex-1 justify-center px-4 py-12 sm:px-6 sm:py-16"
            >
                <div className="flex w-full max-w-sm flex-col gap-8">
                    <div className="flex flex-col gap-2">
                        <Heading level={1} variant="section" align="center">
                            {title}
                        </Heading>
                        {description && (
                            <Text tone="muted" align="center">
                                {description}
                            </Text>
                        )}
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
