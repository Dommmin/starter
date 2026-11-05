import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { I18nProvider } from '@/i18n';
import AdminPagesCreate from './create';
import AdminPagesEdit from './edit';

type Errors = Record<string, string>;

const submitMock = vi.fn();
const httpPostMock = vi.fn();
const visitMock = vi.fn();
let nextErrors: Errors | null = null;
let editorProps: App.Data.Admin.Pages.PageEditorData;

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    function useForm<Data extends object>(initial: Data) {
        const [data, setDataState] = React.useState(initial);
        const [errors, setErrors] = React.useState<Errors>({});
        const transformRef = React.useRef<(value: Data) => unknown>(
            (value) => value,
        );

        return {
            data,
            errors,
            processing: false,
            isDirty: false,
            setData: (
                key: keyof Data | ((current: Data) => Data),
                value?: unknown,
            ) =>
                setDataState((current) =>
                    typeof key === 'function'
                        ? key(current)
                        : { ...current, [key]: value },
                ),
            transform: (callback: (value: Data) => unknown) => {
                transformRef.current = callback;
            },
            setDefaults: () => {},
            submit: (
                route: { url: string; method: string },
                options: {
                    onError?: (errors: Errors) => void;
                    onSuccess?: () => void;
                },
            ) => {
                submitMock(route, transformRef.current(data));

                if (nextErrors) {
                    setErrors(nextErrors);
                    options.onError?.(nextErrors);
                } else {
                    options.onSuccess?.();
                }
            },
        };
    }

    function useHttp() {
        const transformRef = React.useRef<() => unknown>(() => ({}));

        return {
            transform: (callback: () => unknown) => {
                transformRef.current = callback;
            },
            post: (url: string) => httpPostMock(url, transformRef.current()),
        };
    }

    return {
        useForm,
        useHttp,
        usePage: () => ({ props: editorProps }),
        router: {
            visit: (...args: unknown[]) => visitMock(...args),
            delete: vi.fn(),
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

const locales: App.Data.Content.ContentLocalesData = {
    available: [
        { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
        { code: 'de', name: 'German', native: 'Deutsch', dir: 'ltr' },
    ],
    default: 'en',
};

function blankTranslation(): App.Data.Admin.Pages.PageTranslationFormData {
    return {
        title: '',
        slug: '',
        metaDescription: null,
        body: null,
        status: 'draft',
        publishedAt: null,
    };
}

function makeEditor(
    can: Partial<App.Data.Admin.Pages.PageAbilitiesData> = {},
    page: Partial<App.Data.Admin.Pages.PageFormData> = {},
    previewUrls: Record<string, string> = {},
): App.Data.Admin.Pages.PageEditorData {
    return {
        page: {
            id: null,
            updatedAt: null,
            translations: { en: blankTranslation(), de: blankTranslation() },
            ...page,
        },
        locales,
        can: {
            create: true,
            update: true,
            publish: true,
            delete: false,
            suggestSeo: false,
            suggestTranslation: false,
            ...can,
        },
        previewUrls,
        trashDays: 30,
    };
}

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

const mountedRoots: Root[] = [];

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
        );
    });

    return container;
}

function input(container: HTMLElement, name: string): HTMLInputElement {
    return container.querySelector<HTMLInputElement>(
        `[role="tabpanel"] input[name="${name}"]`,
    )!;
}

async function type(element: HTMLInputElement, value: string) {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype,
            'value',
        )!.set!.call(element, value);
        element.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

async function submit(container: HTMLElement) {
    const button = container.querySelector<HTMLButtonElement>(
        '[role="tabpanel"] button[type="submit"]',
    )!;

    await act(async () => {
        button.click();
    });
}

/** Locale code of the selected tab (Radix trigger ids end with `-trigger-{value}`). */
function activeTab(container: HTMLElement): string | undefined {
    return container
        .querySelector('[role="tab"][aria-selected="true"]')
        ?.id.split('-trigger-')[1];
}

function textarea(container: HTMLElement, name: string): HTMLTextAreaElement {
    return container.querySelector<HTMLTextAreaElement>(
        `[role="tabpanel"] textarea[name="${name}"]`,
    )!;
}

function buttonByText(container: HTMLElement, text: string): HTMLButtonElement {
    return Array.from(
        container.querySelectorAll<HTMLButtonElement>(
            '[role="tabpanel"] button',
        ),
    ).find((button) => button.textContent?.trim() === text)!;
}

function assistButtons(container: HTMLElement): HTMLButtonElement[] {
    return Array.from(
        container.querySelectorAll<HTMLButtonElement>(
            '[role="tabpanel"] button',
        ),
    ).filter(
        (button) => button.textContent?.trim() === 'admin.assistant.suggest',
    );
}

async function clickButton(button: HTMLButtonElement) {
    await act(async () => {
        button.click();
    });
}

function translateButton(
    container: HTMLElement,
): HTMLButtonElement | undefined {
    return Array.from(
        container.querySelectorAll<HTMLButtonElement>(
            '[role="tabpanel"] button',
        ),
    ).find(
        (button) =>
            button.textContent?.trim() === 'admin.assistant.translation.action',
    );
}

async function selectTab(container: HTMLElement, code: string) {
    const tab = container.querySelector<HTMLButtonElement>(
        `[role="tab"][id$="-trigger-${code}"]`,
    );
    await act(async () => {
        tab?.focus();
    });
}

const GERMAN_DRAFT: App.Data.Admin.Assistant.TranslationSuggestionData = {
    title: 'Über uns',
    excerpt: null,
    metaDescription: 'Wer wir sind.',
    body: {
        type: 'doc',
        content: [
            {
                type: 'paragraph',
                content: [{ type: 'text', text: 'Hallo Welt' }],
            },
        ],
    },
};

beforeEach(() => {
    nextErrors = null;
});

afterEach(async () => {
    submitMock.mockClear();
    httpPostMock.mockReset();
    visitMock.mockClear();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('PageForm', () => {
    it('opens the preview of the saved active translation in a new tab', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                    },
                    de: blankTranslation(),
                },
            },
            { en: 'http://localhost/admin/pages/5/preview/en?signature=x' },
        );
        const container = await render(<AdminPagesEdit />);

        const link =
            container.querySelector<HTMLAnchorElement>('a[target="_blank"]');
        expect(link?.getAttribute('href')).toBe(
            'http://localhost/admin/pages/5/preview/en?signature=x',
        );
        expect(link?.getAttribute('rel')).toBe('noopener noreferrer');
        expect(link?.textContent).toContain('admin.pages.preview');
        expect(link?.textContent).toContain('a11y.opensInNewTab');
        expect(container.textContent).toContain('admin.pages.previewHint');
    });

    it('disables the preview of an unsaved language version with an explanation', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: blankTranslation(),
                    de: blankTranslation(),
                },
            },
        );
        const container = await render(<AdminPagesEdit />);

        expect(container.querySelector('a[target="_blank"]')).toBeNull();
        const button = [...container.querySelectorAll('button')].find(
            (element) => element.textContent === 'admin.pages.preview',
        );
        expect(button?.disabled).toBe(true);
        expect(container.textContent).toContain(
            'admin.pages.previewUnavailable',
        );
    });

    it('offers no preview link on the create screen', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);

        expect(container.querySelector('a[target="_blank"]')).toBeNull();
        expect(container.textContent).not.toContain('admin.pages.preview');
    });

    it('derives the slug from the title until the slug is edited by hand', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);

        await type(input(container, 'title'), 'Zażółć gęślą jaźń – Straße!');
        expect(input(container, 'slug').value).toBe(
            'zazolc-gesla-jazn-strasse',
        );

        await type(input(container, 'slug'), 'custom-address');
        await type(input(container, 'title'), 'Another title');
        expect(input(container, 'slug').value).toBe('custom-address');
    });

    it('does not overwrite the slug of an existing translation', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                    },
                    de: blankTranslation(),
                },
            },
        );
        const container = await render(<AdminPagesEdit />);

        await type(input(container, 'title'), 'About the company');
        expect(input(container, 'slug').value).toBe('about');
    });

    it('submits all locales with snake_case keys and the loaded version', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                        metaDescription: 'Who we are',
                    },
                    de: blankTranslation(),
                },
            },
        );
        const container = await render(<AdminPagesEdit />);

        await submit(container);

        expect(submitMock).toHaveBeenCalledTimes(1);
        const [route, payload] = submitMock.mock.calls[0];
        expect(route).toEqual({ url: '/admin/pages/5', method: 'put' });
        expect(payload).toEqual({
            updated_at: '2026-09-01T10:00:00+00:00',
            translations: {
                en: {
                    title: 'About',
                    slug: 'about',
                    meta_description: 'Who we are',
                    body: null,
                    status: 'draft',
                },
                de: {
                    title: '',
                    slug: '',
                    meta_description: '',
                    body: null,
                    status: 'draft',
                },
            },
        });
    });

    it('switches to the language tab with the first error and lists it', async () => {
        editorProps = makeEditor();
        nextErrors = { 'translations.de.slug': 'The slug is taken.' };
        const container = await render(<AdminPagesCreate />);

        expect(activeTab(container)).toBe('en');

        await submit(container);

        expect(activeTab(container)).toBe('de');
        const summary = container.querySelector('[role="tabpanel"] a');
        expect(summary?.textContent).toBe(
            'admin.pages.fields.slug: The slug is taken.',
        );
        const slug = input(container, 'slug');
        expect(slug.getAttribute('aria-invalid')).toBe('true');
        expect(document.activeElement).toBe(slug);
    });

    it('requires the title only in the default language and explains skipping elsewhere', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);

        const describedBy = (element: Element | null) =>
            (element?.getAttribute('aria-describedby') ?? '')
                .split(' ')
                .filter(Boolean)
                .map((id) => document.getElementById(id)?.textContent)
                .join(' ');

        const defaultTitle = input(container, 'title');
        expect(defaultTitle.required).toBe(true);
        expect(describedBy(defaultTitle)).not.toContain(
            'admin.pages.fields.titleHelp',
        );

        const germanTab = container.querySelector<HTMLButtonElement>(
            '[role="tab"][id$="-trigger-de"]',
        );
        await act(async () => {
            germanTab?.focus();
        });
        expect(activeTab(container)).toBe('de');

        const germanTitle = input(container, 'title');
        expect(germanTitle.required).toBe(false);
        expect(describedBy(germanTitle)).toContain(
            'admin.pages.fields.titleHelp',
        );
    });

    it('names the status select after its label', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);

        const status = container.querySelector(
            '[role="tabpanel"] button[role="combobox"]',
        );
        const labelIds = status?.getAttribute('aria-labelledby') ?? '';
        expect(document.getElementById(labelIds)?.textContent).toBe(
            'admin.pages.fields.status',
        );
    });

    it('blocks the published status without the publish ability', async () => {
        editorProps = makeEditor({ publish: false });
        const container = await render(<AdminPagesCreate />);

        const published = container.querySelector<HTMLOptionElement>(
            '[role="tabpanel"] select[name="status"] option[value="published"]',
        );
        expect(published?.disabled).toBe(true);
    });

    it('offers to reload the latest version after an edit conflict', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                    },
                    de: blankTranslation(),
                },
            },
        );
        nextErrors = { conflict: 'Changed by someone else.' };
        const container = await render(<AdminPagesEdit />);

        await submit(container);

        const dialog = document.body.querySelector('[role="dialog"]');
        expect(dialog?.textContent).toContain('admin.pages.conflictTitle');

        const reload = Array.from(
            dialog?.querySelectorAll('button') ?? [],
        ).find((button) => button.textContent === 'admin.pages.conflictReload');

        await act(async () => {
            reload?.click();
        });

        expect(visitMock).toHaveBeenCalledWith('/admin/pages/5/edit', {
            preserveState: false,
        });
    });
    it('hides the AI suggestions without the ability', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);

        expect(assistButtons(container)).toHaveLength(0);
    });

    it('fills the meta description and slug from one AI suggestion only after "Use"', async () => {
        httpPostMock.mockResolvedValue({
            metaDescription: 'Who we are and what we do.',
            slug: 'about-our-team',
        });
        editorProps = makeEditor({ suggestSeo: true });
        const container = await render(<AdminPagesCreate />);
        await type(input(container, 'title'), 'About us');

        const [slugAssist, metaAssist] = assistButtons(container);
        await clickButton(metaAssist);

        expect(httpPostMock).toHaveBeenCalledWith('/admin/assistant/seo-meta', {
            subject: 'page',
            locale: 'en',
            title: 'About us',
            text: '',
        });
        expect(textarea(container, 'meta_description').value).toBe('');

        await clickButton(buttonByText(container, 'admin.assistant.apply'));
        expect(textarea(container, 'meta_description').value).toBe(
            'Who we are and what we do.',
        );

        await clickButton(slugAssist);
        await clickButton(buttonByText(container, 'admin.assistant.apply'));

        expect(input(container, 'slug').value).toBe('about-our-team');
        expect(httpPostMock).toHaveBeenCalledTimes(1);
    });

    it('asks for a title before requesting an AI suggestion', async () => {
        editorProps = makeEditor({ suggestSeo: true });
        const container = await render(<AdminPagesCreate />);

        await clickButton(assistButtons(container)[1]);

        expect(httpPostMock).not.toHaveBeenCalled();
        expect(
            container.querySelector('[role="tabpanel"] [role="alert"]')
                ?.textContent,
        ).toBe('admin.assistant.errors.missingTitle');
    });
    it('offers the AI translation only in other language tabs and only with the ability', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);
        await selectTab(container, 'de');

        expect(translateButton(container)).toBeUndefined();

        await act(async () => {
            mountedRoots.splice(0).forEach((root) => root.unmount());
        });
        editorProps = makeEditor({ suggestTranslation: true });
        const withAbility = await render(<AdminPagesCreate />);

        expect(translateButton(withAbility)).toBeUndefined();
        await selectTab(withAbility, 'de');
        expect(translateButton(withAbility)).toBeDefined();
    });

    it('fills an empty language tab with the AI translation of the default language', async () => {
        httpPostMock.mockResolvedValue(GERMAN_DRAFT);
        editorProps = makeEditor({ suggestTranslation: true });
        const container = await render(<AdminPagesCreate />);
        await type(input(container, 'title'), 'About us');
        await selectTab(container, 'de');

        await clickButton(translateButton(container)!);

        expect(httpPostMock).toHaveBeenCalledWith(
            '/admin/assistant/translation',
            {
                subject: 'page',
                source_locale: 'en',
                target_locale: 'de',
                title: 'About us',
                meta_description: '',
                body: { type: 'doc', content: [] },
            },
        );
        expect(input(container, 'title').value).toBe('Über uns');
        expect(input(container, 'slug').value).not.toBe('');
        expect(textarea(container, 'meta_description').value).toBe(
            'Wer wir sind.',
        );
        expect(
            container.querySelector('[role="tabpanel"] [role="status"]')
                ?.textContent,
        ).toContain('admin.assistant.translation.done');
        expect(submitMock).not.toHaveBeenCalled();

        await submit(container);
        expect(submitMock.mock.calls[0][1].translations.de).toMatchObject({
            title: 'Über uns',
            meta_description: 'Wer wir sind.',
            body: GERMAN_DRAFT.body,
        });
    });

    it('asks before replacing an existing translation', async () => {
        httpPostMock.mockResolvedValue(GERMAN_DRAFT);
        editorProps = makeEditor(
            { suggestTranslation: true },
            {
                translations: {
                    en: { ...blankTranslation(), title: 'About us' },
                    de: { ...blankTranslation(), title: 'Alter Titel' },
                },
            },
        );
        const container = await render(<AdminPagesCreate />);
        await selectTab(container, 'de');

        await clickButton(translateButton(container)!);

        expect(httpPostMock).not.toHaveBeenCalled();
        const dialog = document.body.querySelector('[role="dialog"]');
        expect(dialog?.textContent).toContain(
            'admin.assistant.translation.overwriteDescription',
        );

        const cancel = Array.from(
            dialog?.querySelectorAll('button') ?? [],
        ).find(
            (button) =>
                button.textContent === 'admin.assistant.translation.cancel',
        )!;
        await clickButton(cancel);
        expect(httpPostMock).not.toHaveBeenCalled();
        expect(input(container, 'title').value).toBe('Alter Titel');

        await clickButton(translateButton(container)!);
        const confirm = Array.from(
            document.body.querySelectorAll<HTMLButtonElement>(
                '[role="dialog"] button',
            ),
        ).find(
            (button) =>
                button.textContent ===
                'admin.assistant.translation.overwriteConfirm',
        )!;
        await clickButton(confirm);

        expect(httpPostMock).toHaveBeenCalledTimes(1);
        expect(input(container, 'title').value).toBe('Über uns');
    });

    it('explains a missing source without requesting a translation', async () => {
        editorProps = makeEditor({ suggestTranslation: true });
        const container = await render(<AdminPagesCreate />);
        await selectTab(container, 'de');

        await clickButton(translateButton(container)!);

        expect(httpPostMock).not.toHaveBeenCalled();
        expect(
            container.querySelector('[role="tabpanel"] [role="alert"]')
                ?.textContent,
        ).toContain('admin.assistant.translation.missingSource');
    });
});
