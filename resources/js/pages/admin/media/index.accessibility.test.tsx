import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminMediaIndex from './index';

type IndexProps = App.Data.Admin.Media.MediaAssetIndexData;
type VisitOptions = {
    forceFormData?: boolean;
    async?: boolean;
    onProgress?: (progress: { percentage?: number }) => void;
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
};

const postMock = vi.fn();

let pageProps: IndexProps;

function makeProps(can: IndexProps['can']): IndexProps {
    return {
        items: [
            {
                id: 5,
                originalName: 'clean.jpg',
                mime: 'image/jpeg',
                isImage: true,
                size: 2048,
                width: 640,
                height: 360,
                status: 'clean',
                hasScanError: false,
                alt: null,
                thumbnailUrl: '/storage/media/u/abc-320.jpg',
                createdAt: '2026-09-01T10:00:00Z',
            },
            {
                id: 6,
                originalName: 'waiting.pdf',
                mime: 'application/pdf',
                isImage: false,
                size: 4096,
                width: null,
                height: null,
                status: 'quarantine',
                hasScanError: true,
                alt: null,
                thumbnailUrl: null,
                createdAt: '2026-09-01T10:00:00Z',
            },
        ],
        pagination: { page: 1, totalPages: 1, total: 2, perPage: 24 },
        filters: {
            search: '',
            sort: 'created_at',
            direction: 'desc',
            status: 'all',
            type: 'all',
        },
        can,
        upload: { maxBytes: 1024 * 1024, extensions: ['jpg', 'pdf'] },
    };
}

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    router: {
        get: vi.fn(),
        post: (...args: unknown[]) => postMock(...args),
        delete: vi.fn(),
        visit: vi.fn(),
        on: () => () => {},
    },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({
        href,
        children,
        prefetch: _prefetch,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
        prefetch?: boolean;
        [key: string]: unknown;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

const initialPage = {
    props: {
        i18n: {
            area: 'admin',
            locale: 'en',
            defaultLocale: 'en',
            messages: {},
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

async function render(): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AdminMediaIndex />
            </I18nProvider>,
        );
    });

    return container;
}

async function chooseFiles(container: HTMLElement, files: File[]) {
    const input =
        container.querySelector<HTMLInputElement>('input[type="file"]')!;
    Object.defineProperty(input, 'files', { value: files, configurable: true });

    await act(async () => {
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function queueMessages(container: HTMLElement): string[] {
    return Array.from(
        container.querySelectorAll('ul[aria-label] [aria-live="polite"]'),
    ).map((node) => node.textContent ?? '');
}

afterEach(async () => {
    postMock.mockReset();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('AdminMediaIndex', () => {
    it('lists files with thumbnails, status badges and editor links', async () => {
        pageProps = makeProps({ create: true, update: true, delete: true });
        const container = await render();

        expect(container.querySelector('table caption')?.textContent).toBe(
            'admin.media.tableCaption',
        );
        const link = Array.from(container.querySelectorAll('a')).find(
            (anchor) => anchor.textContent === 'clean.jpg',
        );
        expect(link?.getAttribute('href')).toBe('/admin/media/5/edit');
        expect(
            container
                .querySelector('img[src="/storage/media/u/abc-320.jpg"]')
                ?.getAttribute('alt'),
        ).toBe('');
        expect(container.textContent).toContain(
            'admin.media.status.quarantine',
        );
        expect(container.textContent).toContain('admin.media.scanRetrying');
        expect(
            container.querySelectorAll(
                'button[aria-label="admin.media.rowActionsLabel"]',
            ),
        ).toHaveLength(2);
    });

    it('hides the upload area without the create ability', async () => {
        pageProps = makeProps({ create: false, update: false, delete: false });
        const container = await render();

        expect(container.querySelector('input[type="file"]')).toBeNull();
    });

    it('rejects disallowed or oversized files per file and uploads the rest with progress', async () => {
        pageProps = makeProps({ create: true, update: true, delete: false });
        const container = await render();

        await chooseFiles(container, [
            new File(['<?php'], 'shell.php', { type: 'text/x-php' }),
            new File([new Uint8Array(2 * 1024 * 1024)], 'big.pdf', {
                type: 'application/pdf',
            }),
            new File(['jpeg'], 'photo.jpg', { type: 'image/jpeg' }),
        ]);

        expect(postMock).toHaveBeenCalledTimes(1);
        const [url, data, options] = postMock.mock.calls[0] as [
            string,
            { file: File },
            VisitOptions,
        ];
        expect(url).toBe('/admin/media');
        expect(data.file.name).toBe('photo.jpg');
        expect(options.forceFormData).toBe(true);
        expect(options.async).toBe(true);

        await act(async () => options.onProgress?.({ percentage: 40 }));
        expect(
            container
                .querySelector('[role="progressbar"]')
                ?.getAttribute('aria-valuenow'),
        ).toBe('40');

        await act(async () => {
            options.onError?.({ file: 'The file extension does not match.' });
            options.onFinish?.();
        });

        expect(queueMessages(container)).toEqual([
            'admin.media.uploadClientType',
            'admin.media.uploadClientTooLarge',
            'The file extension does not match.',
        ]);
    });

    it('uploads queued files one after another', async () => {
        pageProps = makeProps({ create: true, update: true, delete: false });
        const container = await render();

        await chooseFiles(container, [
            new File(['a'], 'a.jpg', { type: 'image/jpeg' }),
            new File(['b'], 'b.jpg', { type: 'image/jpeg' }),
        ]);

        expect(postMock).toHaveBeenCalledTimes(1);
        const first = postMock.mock.calls[0][2] as VisitOptions;

        await act(async () => {
            first.onSuccess?.();
            first.onFinish?.();
        });

        expect(postMock).toHaveBeenCalledTimes(2);
        expect((postMock.mock.calls[1][1] as { file: File }).file.name).toBe(
            'b.jpg',
        );
        expect(queueMessages(container)[0]).toBe('admin.media.uploadDone');
    });
});
