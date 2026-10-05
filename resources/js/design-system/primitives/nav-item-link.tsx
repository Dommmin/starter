import { Link as InertiaLink, usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
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

function pathOf(url: string): string {
    const path = new URL(url, 'http://localhost').pathname.replace(/\/+$/, '');

    return path === '' ? '/' : path;
}

/**
 * True when an internal entry points at the current page or one of its
 * descendants (`/articles` for `/articles/{slug}`). Fragment links, external
 * links and locale home pages (`/`, `/pl`) only match exactly.
 */
export function isCurrentNavItem(
    item: NavItem,
    currentUrl: string,
    locale: string,
): boolean {
    if (item.kind !== 'internal' || !item.href || item.href.includes('#')) {
        return false;
    }

    const target = pathOf(item.href);
    const current = pathOf(currentUrl);

    if (target === current) {
        return true;
    }

    const isLocaleHome = target === '/' || target === `/${locale}`;

    return !isLocaleHome && current.startsWith(`${target}/`);
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
    const { t, locale } = useTranslation();
    const { url } = usePage();
    const isCurrent = isCurrentNavItem(item, url, locale);

    if (item.kind === 'internal' && !item.newTab) {
        return (
            <InertiaLink
                href={item.href}
                className={classes}
                aria-current={isCurrent ? 'page' : undefined}
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
            {isExternal && (
                <ExternalLink
                    className="text-muted-foreground ml-1 inline size-3.5 align-[-0.125em]"
                    aria-hidden="true"
                />
            )}
            {opensNewTab && (
                <span className="sr-only"> ({t('a11y.opensInNewTab')})</span>
            )}
        </a>
    );
}
