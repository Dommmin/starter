import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { I18nProvider } from '@/i18n';
import AdminFaqsIndex from './index';

type IndexProps = App.Data.Admin.Faqs.FaqIndexData;

const getMock = vi.fn();
const postMock = vi.fn();
const httpPostMock = vi.fn();

let pageProps: IndexProps;

function makeProps(can: IndexProps['can']): IndexProps {
    return {
        items: [
            {
                id: 7,
                question: 'First question',
                position: 1,
                published: true,
                createdAt: '2026-09-01T10:00:00Z',
                updatedAt: '2026-09-01T10:00:00Z',
            },
            {
                id: 8,
                question: 'Second question',
                position: 2,
                published: false,
                createdAt: '2026-09-01T10:00:00Z',
                updatedAt: '2026-09-01T10:00:00Z',
            },
        ],
        pagination: { page: 1, totalPages: 1, total: 2, perPage: 15 },
        filters: {
            search: '',
            sort: 'created_at',
            direction: 'desc',
            published: 'all',
        },
        can,
        locales: {
            available: [
                { code: 'pl', name: 'Polish', native: 'Polski', dir: 'ltr' },
                { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
            ],
            default: 'pl',
        },
    };
}

function abilities(
    overrides: Partial<IndexProps['can']> = {},
): IndexProps['can'] {
    return {
        create: true,
        update: true,
        delete: true,
        import: true,
        suggestFaqs: false,
        ...overrides,
    };
}

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    function useHttp<Data extends Record<string, string>>(initial: Data) {
        const [data, setDataState] = React.useState(initial);
        const [processing, setProcessing] = React.useState(false);

        return {
            data,
            errors: {} as Partial<Record<keyof Data, string>>,
            processing,
            setData: (key: keyof Data, value: string) =>
                setDataState((current) => ({ ...current, [key]: value })),
            reset: () => setDataState(initial),
            clearErrors: () => {},
            post: async (url: string) => {
                setProcessing(true);
                try {
                    return await httpPostMock(url, data);
                } finally {
                    setProcessing(false);
                }
            },
        };
    }

    return {
        useHttp,
        usePage: () => ({ props: pageProps }),
        router: {
            get: (...args: unknown[]) => getMock(...args),
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

async function render(): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AdminFaqsIndex />
            </I18nProvider>,
        );
    });

    return container;
}

async function unmountAll() {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
}

afterEach(async () => {
    getMock.mockClear();
    postMock.mockClear();
    httpPostMock.mockReset();
    await unmountAll();
});

describe('AdminFaqsIndex', () => {
    it('renders a captioned table whose first column links to the editor', async () => {
        pageProps = makeProps(abilities());
        const container = await render();

        expect(container.querySelector('table caption')?.textContent).toBe(
            'admin.faqs.tableCaption',
        );
        const editLink = Array.from(container.querySelectorAll('a')).find(
            (link) => link.textContent === 'First question',
        );
        expect(editLink?.getAttribute('href')).toBe('/admin/faqs/7/edit');
    });

    it('offers the create action and row menus only with the abilities', async () => {
        pageProps = makeProps(abilities());
        let container = await render();

        const createLink = Array.from(container.querySelectorAll('a')).find(
            (link) => link.textContent === 'admin.faqs.create',
        );
        expect(createLink?.getAttribute('href')).toBe('/admin/faqs/create');
        expect(
            container.querySelectorAll(
                'button[aria-label="admin.faqs.rowActionsLabel"]',
            ),
        ).toHaveLength(2);

        await unmountAll();

        pageProps = makeProps(
            abilities({
                create: false,
                update: false,
                delete: false,
                import: false,
            }),
        );
        container = await render();

        expect(container.textContent).not.toContain('admin.faqs.create');
    });

    it('clears the filters to the server defaults', async () => {
        pageProps = makeProps(abilities());
        pageProps.filters = {
            ...pageProps.filters,
            published: 'yes',
        };
        const container = await render();

        const clearButton = Array.from(
            container.querySelectorAll('button'),
        ).find((button) => button.textContent === 'admin.faqs.clearFilters');

        await act(async () => {
            clearButton?.click();
        });

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toMatchObject({
            published: 'all',
            sort: 'created_at',
            direction: 'desc',
            page: 1,
        });
    });

    it('hides the AI generator without the ability', async () => {
        pageProps = makeProps(abilities({ suggestFaqs: false }));
        const container = await render();

        expect(container.textContent).not.toContain(
            'admin.faqs.generator.open',
        );
        expect(document.body.textContent).not.toContain(
            'admin.faqs.generator.title',
        );
    });

    it('saves nothing until confirmed and then only the ticked proposals', async () => {
        pageProps = makeProps(abilities({ suggestFaqs: true }));
        httpPostMock.mockResolvedValue({
            items: [
                { question: 'When do you bake?', answer: 'Before sunrise.' },
                { question: 'Do you deliver?', answer: 'Yes, in the city.' },
                { question: 'Is it vegan?', answer: 'The sourdough is.' },
            ],
        });
        await render();

        await click(buttonByText('admin.faqs.generator.open'));
        const textarea = document.querySelector<HTMLTextAreaElement>(
            'textarea[name="text"]',
        )!;
        await act(async () => {
            Object.getOwnPropertyDescriptor(
                window.HTMLTextAreaElement.prototype,
                'value',
            )!.set!.call(textarea, 'Our bakery bakes before sunrise.');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await submitDialog();

        expect(httpPostMock).toHaveBeenCalledWith('/admin/assistant/faqs', {
            locale: 'pl',
            text: 'Our bakery bakes before sunrise.',
        });
        const checkboxes = document.querySelectorAll<HTMLButtonElement>(
            'button[role="checkbox"]',
        );
        expect(checkboxes).toHaveLength(3);
        expect(document.body.textContent).toContain('Before sunrise.');
        expect(postMock).not.toHaveBeenCalled();

        // Saving with nothing ticked only explains what to do.
        await submitDialog();
        expect(postMock).not.toHaveBeenCalled();
        expect(document.querySelector('[role="alert"]')?.textContent).toBe(
            'admin.faqs.generator.noneSelected',
        );

        await click(checkboxes[0]);
        await click(checkboxes[2]);
        await submitDialog();

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock.mock.calls[0][0]).toBe('/admin/faqs/many');
        expect(postMock.mock.calls[0][1]).toEqual({
            locale: 'pl',
            items: [
                { question: 'When do you bake?', answer: 'Before sunrise.' },
                { question: 'Is it vegan?', answer: 'The sourdough is.' },
            ],
        });
    });

    it('asks for a source text before calling the assistant', async () => {
        pageProps = makeProps(abilities({ suggestFaqs: true }));
        await render();

        await click(buttonByText('admin.faqs.generator.open'));
        await submitDialog();

        expect(httpPostMock).not.toHaveBeenCalled();
        expect(document.body.textContent).toContain(
            'admin.faqs.generator.missingText',
        );
    });
});

function buttonByText(text: string): HTMLButtonElement {
    return Array.from(document.querySelectorAll('button')).find(
        (button) => button.textContent === text,
    )!;
}

async function click(element: HTMLElement) {
    await act(async () => {
        element.click();
    });
}

async function submitDialog() {
    await act(async () => {
        document
            .querySelector('[role="dialog"] form')!
            .dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
    });
}
