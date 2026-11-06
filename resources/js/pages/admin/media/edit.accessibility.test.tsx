import { HttpResponseError } from '@inertiajs/core';
import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { I18nProvider } from '@/i18n';
import AdminMediaEdit from './edit';

type EditorProps = App.Data.Admin.Media.MediaAssetEditorData;

const httpPostMock = vi.fn();
let pageProps: EditorProps;

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    function useForm<Data extends object>(initial: Data) {
        const [data, setDataState] = React.useState(initial);

        return {
            data,
            errors: {},
            processing: false,
            setData: (key: keyof Data, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
            put: vi.fn(),
        };
    }

    return {
        useForm,
        useHttp: () => ({ post: (url: string) => httpPostMock(url) }),
        usePage: () => ({ props: pageProps }),
        usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: false }),
        router: { delete: vi.fn(), on: () => () => {} },
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
    };
});

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

function makeProps(suggestAlt: boolean): EditorProps {
    return {
        asset: {
            id: 7,
            updatedAt: '2026-09-01T10:00:00Z',
            originalName: 'bread.jpg',
            mime: 'image/jpeg',
            isImage: true,
            size: 2048,
            width: 640,
            height: 360,
            checksum: 'abc',
            status: 'clean',
            scanError: null,
            alt: null,
            ownerName: null,
            createdAt: '2026-09-01T10:00:00Z',
            image: null,
            variants: [],
            downloadUrl: null,
        },
        can: { create: true, update: true, delete: false, suggestAlt },
    };
}

async function render(): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AdminMediaEdit />
            </I18nProvider>,
        );
    });

    return container;
}

function buttonByText(
    container: HTMLElement,
    text: string,
): HTMLButtonElement | undefined {
    return Array.from(container.querySelectorAll('button')).find(
        (button) => button.textContent === text,
    );
}

async function clickButton(button: HTMLButtonElement | undefined) {
    await act(async () => {
        button?.click();
    });
}

function altField(container: HTMLElement): HTMLTextAreaElement {
    return container.querySelector<HTMLTextAreaElement>(
        'textarea[name="alt"]',
    )!;
}

afterEach(async () => {
    httpPostMock.mockReset();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('AdminMediaEdit alt text suggestion', () => {
    it('hides the AI suggestion without the ability', async () => {
        pageProps = makeProps(false);
        const container = await render();

        expect(
            buttonByText(container, 'admin.assistant.suggestAlt'),
        ).toBeUndefined();
    });

    it('fills the alt text only after "Use"', async () => {
        httpPostMock.mockResolvedValue({ alt: 'A loaf of bread.' });
        pageProps = makeProps(true);
        const container = await render();

        await clickButton(
            buttonByText(container, 'admin.assistant.suggestAlt'),
        );

        expect(httpPostMock).toHaveBeenCalledWith(
            '/admin/assistant/media/7/alt-text',
        );
        expect(altField(container).value).toBe('');
        expect(container.textContent).toContain('A loaf of bread.');

        await clickButton(buttonByText(container, 'admin.assistant.apply'));

        expect(altField(container).value).toBe('A loaf of bread.');
    });

    it('explains when the file cannot be described', async () => {
        httpPostMock.mockRejectedValue(
            new HttpResponseError('Not Found', {
                status: 404,
                data: '{}',
                headers: {},
            }),
        );
        pageProps = makeProps(true);
        const container = await render();

        await clickButton(
            buttonByText(container, 'admin.assistant.suggestAlt'),
        );

        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'admin.assistant.errors.notDescribable',
        );
        expect(altField(container).value).toBe('');
    });
});
