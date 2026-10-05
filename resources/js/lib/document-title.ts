import type { Page } from '@inertiajs/core';

/**
 * `createInertiaApp({ title })` callback: "{page title} - {site name}". The
 * site name comes from the shared `site` settings (saved in the panel), then
 * the shared SEO defaults, so the title follows the panel on the server
 * (SSR) and in the browser. A title that already names the site (a home
 * page without its own SEO title, or the catalog default title) gets no
 * suffix.
 */
export function documentTitle(title: string, page?: Page): string {
    const props = page?.props as
        | {
              site?: { name?: string };
              seo?: { siteName?: string };
          }
        | undefined;
    const siteName = props?.site?.name || props?.seo?.siteName || '';

    if (!siteName) {
        return title;
    }

    if (!title) {
        return siteName;
    }

    if (title.includes(siteName)) {
        return title;
    }

    return `${title} - ${siteName}`;
}
