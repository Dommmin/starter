/**
 * Whether `current` is `path` itself or one of its sub-pages; the match
 * respects path segments, so `/admin/contact-forms` is not a child of
 * `/admin/contact`.
 */
export function isSameOrChildPath(current: string, path: string): boolean {
    const parent = path.endsWith('/') ? path : `${path}/`;

    return current === path || current.startsWith(parent);
}
