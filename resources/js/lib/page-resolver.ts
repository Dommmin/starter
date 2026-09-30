import type { ResolvedComponent } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';

type PageModule = { default: ResolvedComponent };
type LayoutComponent = ComponentType<{ children: ReactNode }>;
type LayoutGroup = 'none' | 'auth' | 'settings' | 'admin' | 'app';

/**
 * Page modules, lazy-loaded per visit. Tests colocated with pages
 * (`*.test.tsx`) are excluded so they never reach the client or SSR bundle;
 * design-system showcase sections are modules of their page, not pages.
 */
const pages = import.meta.glob<PageModule>([
    '../pages/**/*.tsx',
    '!../pages/**/*.test.tsx',
    '!../pages/admin/design-system/sections/**',
    '!../pages/design-system/sections/**',
]);

/**
 * Layouts are split out of the entry chunk: public pages (welcome, CMS pages,
 * errors) do not download the admin shell. A layout is loaded before its page
 * resolves, so `resolveLayout` can stay synchronous for Inertia.
 */
const layoutLoaders: Record<
    Exclude<LayoutGroup, 'none'>,
    () => Promise<LayoutComponent[]>
> = {
    auth: async () => [(await import('@/layouts/auth-layout')).default],
    settings: async () => {
        const [publicLayout, settings] = await Promise.all([
            import('@/layouts/public-layout'),
            import('@/layouts/settings/layout'),
        ]);

        return [publicLayout.default, settings.default];
    },
    admin: async () => [(await import('@/layouts/admin-layout')).default],
    app: async () => [(await import('@/layouts/app-layout')).default],
};

const loadedLayouts = new Map<LayoutGroup, LayoutComponent[]>();

function layoutGroup(name: string): LayoutGroup {
    switch (true) {
        case name === 'welcome':
        case name.startsWith('pages/'):
        case name.startsWith('articles/'):
        case name.startsWith('errors/'):
        case name.startsWith('design-system/'):
            return 'none';
        case name.startsWith('auth/'):
            return 'auth';
        case name.startsWith('settings/'):
            return 'settings';
        case name.startsWith('admin/'):
            return 'admin';
        default:
            return 'app';
    }
}

/**
 * Visual surface of a page. Only the admin panel uses the admin surface;
 * account settings live in the public frame. app.blade.php renders the same decision on <html> for the first
 * response, `syncSurface` keeps it current across Inertia visits.
 */
export function surfaceFor(name: string): 'admin' | null {
    const group = layoutGroup(name);

    return group === 'admin' ? 'admin' : null;
}

export function syncSurface(name: string): void {
    const surface = surfaceFor(name);
    const root = document.documentElement;

    if (surface) {
        root.dataset.surface = surface;
    } else {
        delete root.dataset.surface;
    }
}

export async function loadPage(name: string): Promise<ResolvedComponent> {
    const importPage = pages[`../pages/${name}.tsx`];

    if (!importPage) {
        throw new Error(`Page not found: ${name}`);
    }

    const group = layoutGroup(name);
    const [page, layouts] = await Promise.all([
        importPage(),
        group === 'none' || loadedLayouts.has(group)
            ? Promise.resolve(null)
            : layoutLoaders[group](),
    ]);

    if (layouts) {
        loadedLayouts.set(group, layouts);
    }

    return page.default;
}

export function resolveLayout(
    name: string,
): LayoutComponent | LayoutComponent[] | null {
    const group = layoutGroup(name);

    if (group === 'none') {
        return null;
    }

    const layouts = loadedLayouts.get(group);

    if (!layouts) {
        throw new Error(`Layout for page ${name} was not loaded.`);
    }

    return layouts.length === 1 ? layouts[0] : layouts;
}
