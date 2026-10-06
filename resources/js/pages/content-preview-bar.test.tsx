import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ContentPreviewBar } from '@/components/content/content-preview-bar';
import { I18nProvider } from '@/i18n';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        prefetch: _prefetch,
        ...props
    }: {
        children: ReactNode;
        href: string;
        prefetch?: boolean;
        [key: string]: unknown;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    router: { on: () => () => {} },
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const initialPage = {
    props: {
        i18n: {
            area: 'public',
            locale: 'en',
            defaultLocale: 'en',
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
            messages: {
                preview: {
                    titleUnpublished: 'Preview — not public',
                    titlePublished: 'Preview — published version',
                    state: {
                        draft: 'Status: draft.',
                        scheduled: 'Status: scheduled for :date.',
                        scheduledUndated:
                            'Status: scheduled, no publication date set.',
                        published: 'Status: published.',
                    },
                    savedVersion: 'You are viewing the last saved version.',
                    backToEdit: 'Back to editing',
                },
            },
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const mountedRoots: Root[] = [];

async function render(
    preview: App.Data.Content.ContentPreviewData,
): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <ContentPreviewBar preview={preview} />
            </I18nProvider>,
        );
    });

    return container;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('ContentPreviewBar', () => {
    it('announces an unpublished draft and links back to its editor', async () => {
        const container = await render({
            state: 'draft',
            publishAt: null,
            editUrl: 'http://localhost/admin/pages/5/edit',
        });

        const status = container.querySelector('[role="status"]');
        expect(status?.textContent).toContain('Preview — not public');
        expect(status?.textContent).toContain('Status: draft.');
        expect(status?.textContent).toContain(
            'You are viewing the last saved version.',
        );

        const link = status?.querySelector('a');
        expect(link?.textContent).toBe('Back to editing');
        expect(link?.getAttribute('href')).toBe(
            'http://localhost/admin/pages/5/edit',
        );
        expect(link?.hasAttribute('target')).toBe(false);
    });

    it('shows the planned publication date of a scheduled translation', async () => {
        const container = await render({
            state: 'scheduled',
            publishAt: '2026-12-24T09:00:00+00:00',
            editUrl: '/admin/articles/3/edit',
        });

        const text = container.querySelector('[role="status"]')?.textContent;
        expect(text).toContain('Preview — not public');
        expect(text).toContain('Status: scheduled for');
        expect(text).toContain('2026');
    });

    it('marks an already visible translation as published', async () => {
        const container = await render({
            state: 'published',
            publishAt: null,
            editUrl: '/admin/articles/3/edit',
        });

        const text = container.querySelector('[role="status"]')?.textContent;
        expect(text).toContain('Preview — published version');
        expect(text).toContain('Status: published.');
    });
});
