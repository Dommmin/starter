import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n';
import { cn } from '@/lib/utils';
import { BrandLogo, type BrandLogoImage } from './brand-logo';
import { Container } from './container';
import type { NavItem } from './nav-item';
import { isNavLink, NavItemLink } from './nav-item-link';
import type { OpeningHoursRow } from './opening-hours';
import { Text } from './text';

export type FooterContact = {
    email?: string;
    phone?: string;
    /** Postal address; line breaks (`\n`) are preserved. */
    address?: string;
    /** Tax identification number, shown with a translated label. */
    taxId?: string;
    /** https link to the location on a map; opens in a new tab. */
    mapUrl?: string;
    /** Grouped opening hours, see `groupOpeningHours`. */
    openingHours?: OpeningHoursRow[];
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
     * Link columns: a `group` (or any entry with `children`) titles its own
     * column and lists its children; top-level plain links are gathered in
     * one "quick links" column placed first, so no column repeats a link's
     * label as its heading.
     */
    groups?: NavItem[];
    contact?: FooterContact;
    social?: FooterSocialLink[];
    /** Optional logo image passed to `BrandLogo`; the text logo otherwise. */
    logo?: BrandLogoImage;
    /** One-tone text logo name; the catalog brand otherwise. */
    brandName?: string;
    /** One-sentence brand promise under the logo, e.g. the site tagline. */
    description?: string;
    /** Translated copyright line, e.g. "© 2026 Acme. All rights reserved." */
    copyright: string;
    /** Rendered after the copyright line, e.g. a LocaleSwitcher. */
    trailing?: ReactNode;
    /** Reopens the cookie choice; shown next to the copyright line. */
    cookieSettings?: { label: string; onOpen: () => void };
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
        <div className="flex min-w-0 flex-col gap-3 wrap-anywhere">
            <h2 className="text-foreground text-xs font-medium sm:text-sm">
                {title}
            </h2>
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
    brandName,
    description,
    copyright,
    trailing,
    cookieSettings,
}: FooterProps) {
    const { t } = useTranslation();
    // Contact details lead the brand block (the e-mail as the main way to
    // get in touch); the columns hold navigation and social profiles only.
    const hasColumns = groups.length > 0 || social.length > 0;
    const groupColumns = groups.filter(
        (group) => (group.children ?? []).length > 0,
    );
    const looseLinks = groups
        .filter((group) => (group.children ?? []).length === 0)
        .filter(isNavLink);

    return (
        <footer className="bg-surface-subtle border-border-subtle border-t">
            <Container width="wide" padding="page">
                <div className="flex flex-col gap-10 py-12 sm:py-16">
                    <div className="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-16">
                        <div className="flex max-w-md flex-col items-start gap-5 lg:col-span-5">
                            <BrandLogo image={logo} name={brandName} />
                            {description && (
                                <Text tone="muted">{description}</Text>
                            )}
                            {contact?.email && (
                                <a
                                    href={`mailto:${contact.email}`}
                                    className="text-foreground hover:text-primary focus-visible:ring-ring decoration-primary/40 font-heading rounded-sm text-xl font-semibold tracking-tight wrap-anywhere underline underline-offset-8 transition-colors duration-150 hover:decoration-current focus-visible:ring-2 focus-visible:outline-none sm:text-2xl"
                                >
                                    {contact.email}
                                </a>
                            )}
                            {(contact?.phone ||
                                contact?.address ||
                                contact?.taxId ||
                                contact?.mapUrl) && (
                                <div className="flex flex-col gap-2">
                                    {contact.phone && (
                                        <a
                                            href={telHref(contact.phone)}
                                            className={linkClasses}
                                        >
                                            {contact.phone}
                                        </a>
                                    )}
                                    {contact.address && (
                                        <address className="text-muted-foreground text-sm whitespace-pre-line not-italic">
                                            {contact.address}
                                        </address>
                                    )}
                                    {contact.mapUrl && (
                                        <NavItemLink
                                            item={{
                                                id: 'map',
                                                label: t('footer.map'),
                                                href: contact.mapUrl,
                                                kind: 'external',
                                                newTab: true,
                                            }}
                                            classes={linkClasses}
                                        />
                                    )}
                                    {contact.taxId && (
                                        <Text variant="caption" tone="muted">
                                            {t('footer.taxId', {
                                                taxId: contact.taxId,
                                            })}
                                        </Text>
                                    )}
                                </div>
                            )}
                            {contact?.openingHours &&
                                contact.openingHours.length > 0 && (
                                    <div className="flex flex-col gap-2">
                                        <h2 className="text-foreground text-xs font-medium sm:text-sm">
                                            {t('footer.openingHours')}
                                        </h2>
                                        <dl className="text-muted-foreground grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm tabular-nums">
                                            {contact.openingHours.map((row) => (
                                                <div
                                                    key={row.days}
                                                    className="contents"
                                                >
                                                    <dt>{row.days}</dt>
                                                    <dd>{row.hours}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                    </div>
                                )}
                        </div>
                        {hasColumns && (
                            <div className="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-7">
                                {looseLinks.length > 0 && (
                                    <FooterColumn title={t('footer.links')}>
                                        {looseLinks.map((link) => (
                                            <li key={link.id}>
                                                <NavItemLink
                                                    item={link}
                                                    classes={linkClasses}
                                                />
                                            </li>
                                        ))}
                                    </FooterColumn>
                                )}
                                {groupColumns.map((group) => (
                                    <FooterColumn
                                        key={group.id}
                                        title={group.label}
                                    >
                                        {(group.children ?? [])
                                            .filter(isNavLink)
                                            .map((link) => (
                                                <li key={link.id}>
                                                    <NavItemLink
                                                        item={link}
                                                        classes={linkClasses}
                                                    />
                                                </li>
                                            ))}
                                    </FooterColumn>
                                ))}
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
                    </div>
                    <div className="border-border-subtle flex flex-col items-start justify-between gap-4 border-t pt-6 sm:flex-row sm:items-center">
                        <div className="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-6">
                            <Text variant="caption" tone="muted">
                                {copyright}
                            </Text>
                            {cookieSettings && (
                                <button
                                    type="button"
                                    onClick={cookieSettings.onOpen}
                                    className={cn(
                                        linkClasses,
                                        'min-h-11 text-xs sm:min-h-0',
                                    )}
                                >
                                    {cookieSettings.label}
                                </button>
                            )}
                        </div>
                        {trailing}
                    </div>
                </div>
            </Container>
        </footer>
    );
}
