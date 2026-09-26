import { Link as InertiaLink } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BrandLogo } from './brand-logo';
import { Container } from './container';
import { Text } from './text';

export type FooterLinkGroup = {
    id: string;
    title: string;
    links: { id: string; label: string; href: string }[];
};

export type FooterProps = {
    groups?: FooterLinkGroup[];
    /** Translated copyright line, e.g. "© 2026 Acme. All rights reserved." */
    copyright: string;
    /** Rendered after the copyright line, e.g. a LocaleSwitcher. */
    trailing?: ReactNode;
};

export function Footer({ groups = [], copyright, trailing }: FooterProps) {
    return (
        <footer className="border-border-subtle border-t">
            <Container width="wide" padding="page">
                <div className="flex flex-col gap-8 py-10 sm:py-12">
                    {groups.length > 0 && (
                        <div className="grid grid-cols-2 gap-8 sm:grid-cols-4">
                            {groups.map((group) => (
                                <div
                                    key={group.id}
                                    className="flex flex-col gap-3"
                                >
                                    <Text variant="label" as="p">
                                        {group.title}
                                    </Text>
                                    <ul className="flex flex-col gap-2">
                                        {group.links.map((link) => (
                                            <li key={link.id}>
                                                <InertiaLink
                                                    href={link.href}
                                                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-sm text-sm focus-visible:ring-2 focus-visible:outline-none"
                                                >
                                                    {link.label}
                                                </InertiaLink>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    )}
                    <div className="border-border-subtle flex flex-col items-start justify-between gap-4 border-t pt-6 sm:flex-row sm:items-center">
                        <BrandLogo />
                        <Text variant="caption" tone="muted">
                            {copyright}
                        </Text>
                        {trailing}
                    </div>
                </div>
            </Container>
        </footer>
    );
}
