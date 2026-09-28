import type { Page } from '@inertiajs/core';

/**
 * `createInertiaApp({ title })` callback: "{page title} - {site name}". The
 * site name comes from the shared `site` settings (saved in the panel), then
 * the shared SEO defaults, so the title follows the panel on the server
 * (SSR) and in the browser. A title equal to the site name (a home page
 * without its own SEO title) is not repeated.
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

    if (!title || title === siteName) {
        return siteName;
    }

    return `${title} - ${siteName}`;
}
