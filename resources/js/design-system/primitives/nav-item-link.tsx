import { Link as InertiaLink } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import type { NavItem } from './nav-item';

/*
 * Internal to the design system (not re-exported from the barrel): screens
 * pass `NavItem` data to PublicHeader, MobileNav, Footer and PublicChrome.
 */

/** True when the entry navigates somewhere (not a bare group heading). */
export function isNavLink(item: NavItem): item is NavItem & { href: string } {
    return item.kind !== 'group' && typeof item.href === 'string';
}

type NavItemLinkProps = {
    item: NavItem & { href: string };
    /** Styling owned by the calling primitive. */
    classes: string;
    onNavigate?: () => void;
};

/**
 * Renders one navigation link with the element its kind requires. Internal to
 * the design system: screens pass `NavItem` data to the public primitives.
 */
export function NavItemLink({ item, classes, onNavigate }: NavItemLinkProps) {
    const { t } = useTranslation();

    if (item.kind === 'internal' && !item.newTab) {
        return (
            <InertiaLink
                href={item.href}
                className={classes}
                onClick={onNavigate}
            >
                {item.label}
            </InertiaLink>
        );
    }

    const opensNewTab = item.newTab === true;
    const isExternal = item.kind === 'external' || opensNewTab;

    return (
        <a
            href={item.href}
            className={classes}
            target={opensNewTab ? '_blank' : undefined}
            rel={isExternal ? 'noopener noreferrer' : undefined}
            onClick={onNavigate}
        >
            {item.label}
            {opensNewTab && (
                <span className="sr-only"> ({t('a11y.opensInNewTab')})</span>
            )}
        </a>
    );
}
