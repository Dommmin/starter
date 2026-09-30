/**
 * How a navigation entry is rendered:
 * - `internal` — an Inertia visit within the site,
 * - `anchor` — an in-page fragment (`#features`) or a plain full-page link,
 * - `external` — another origin; always gets `rel="noopener noreferrer"`,
 * - `group` — a heading without a link (first level only), grouping `children`.
 */
export type NavItemKind = 'internal' | 'anchor' | 'external' | 'group';

/**
 * One public navigation entry shared by `PublicHeader`, `MobileNav` and
 * `Footer`. `children` are supported one level deep; nested children are
 * ignored. A `group` has no `href`.
 */
export type NavItem = {
    id: string | number;
    label: string;
    href?: string;
    kind: NavItemKind;
    /** Opens the target in a new tab with an accessible hint. */
    newTab?: boolean;
    children?: NavItem[];
};
