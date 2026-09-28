import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n';
import { BrandLogo, type BrandLogoImage } from './brand-logo';
import { Container } from './container';
import type { NavItem } from './nav-item';
import { isNavLink, NavItemLink } from './nav-item-link';
import { Text } from './text';

export type FooterContact = {
    email?: string;
    phone?: string;
    /** Postal address; line breaks (`\n`) are preserved. */
    address?: string;
};

/** A text link to a social profile (no brand icons); always opens externally. */
export type FooterSocialLink = {
    /** Stable network identifier, e.g. `linkedin`; used as the list key. */
    network: string;
    /** Visible, translated label, e.g. "LinkedIn". */
    label: string;
    url: string;
};

export type FooterProps = {
    /**
     * Link columns: each top-level entry is one column. A `group` (or any
     * entry with `children`) titles the column and lists its children; a
     * plain link becomes a single-link column.
     */
    groups?: NavItem[];
    contact?: FooterContact;
    social?: FooterSocialLink[];
    /** Optional logo image passed to `BrandLogo`; the text logo otherwise. */
    logo?: BrandLogoImage;
    /** Translated copyright line, e.g. "© 2026 Acme. All rights reserved." */
    copyright: string;
    /** Rendered after the copyright line, e.g. a LocaleSwitcher. */
    trailing?: ReactNode;
    className?: never;
    style?: never;
};

const linkClasses =
    'text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-sm text-sm focus-visible:ring-2 focus-visible:outline-none';

function FooterColumn({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3">
            <Text variant="label" as="p">
                {title}
            </Text>
            <ul className="flex flex-col gap-2">{children}</ul>
        </div>
    );
}

function telHref(phone: string): string {
    return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

export function Footer({
    groups = [],
    contact,
    social = [],
    logo,
    copyright,
    trailing,
}: FooterProps) {
    const { t } = useTranslation();
    const hasContact = Boolean(
        contact?.email || contact?.phone || contact?.address,
    );
    const hasColumns = groups.length > 0 || hasContact || social.length > 0;

    return (
        <footer className="border-border-subtle border-t">
            <Container width="wide" padding="page">
                <div className="flex flex-col gap-8 py-10 sm:py-12">
                    {hasColumns && (
                        <div className="grid grid-cols-2 gap-8 sm:grid-cols-4">
                            {groups.map((group) => {
                                const children = group.children ?? [];
                                const links =
                                    children.length > 0 ? children : [group];

                                return (
                                    <FooterColumn
                                        key={group.id}
                                        title={group.label}
                                    >
                                        {links.filter(isNavLink).map((link) => (
                                            <li key={link.id}>
                                                <NavItemLink
                                                    item={link}
                                                    classes={linkClasses}
                                                />
                                            </li>
                                        ))}
                                    </FooterColumn>
                                );
                            })}
                            {hasContact && contact && (
                                <FooterColumn title={t('footer.contact')}>
                                    {contact.email && (
                                        <li>
                                            <a
                                                href={`mailto:${contact.email}`}
                                                className={linkClasses}
                                            >
                                                {contact.email}
                                            </a>
                                        </li>
                                    )}
                                    {contact.phone && (
                                        <li>
                                            <a
                                                href={telHref(contact.phone)}
                                                className={linkClasses}
                                            >
                                                {contact.phone}
                                            </a>
                                        </li>
                                    )}
                                    {contact.address && (
                                        <li>
                                            <address className="text-muted-foreground text-sm whitespace-pre-line not-italic">
                                                {contact.address}
                                            </address>
                                        </li>
                                    )}
                                </FooterColumn>
                            )}
                            {social.length > 0 && (
                                <FooterColumn title={t('footer.social')}>
                                    {social.map((link) => (
                                        <li key={link.network}>
                                            <NavItemLink
                                                item={{
                                                    id: link.network,
                                                    label: link.label,
                                                    href: link.url,
                                                    kind: 'external',
                                                    newTab: true,
                                                }}
                                                classes={linkClasses}
                                            />
                                        </li>
                                    ))}
                                </FooterColumn>
                            )}
                        </div>
                    )}
                    <div className="border-border-subtle flex flex-col items-start justify-between gap-4 border-t pt-6 sm:flex-row sm:items-center">
                        <BrandLogo image={logo} />
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
