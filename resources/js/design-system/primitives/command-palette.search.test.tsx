import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { FileText, LayoutGrid } from 'lucide-react';
import { act } from 'react';
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
import {
    CommandPalette,
    type CommandPaletteItem,
    type CommandPaletteSearch,
} from './command-palette';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: {} }),
    router: { on: () => () => {}, visit: () => {} },
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

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

const navigation: CommandPaletteItem[] = [
    {
        id: 'dashboard',
        label: 'Dashboard',
        group: 'Navigation',
        href: '/admin',
        icon: LayoutGrid,
    },
];

const found: CommandPaletteItem[] = [
    {
        id: 'pages-7',
        label: 'Bakery offer',
        description: 'EN · offer',
        group: 'Pages',
        href: '/admin/pages/7/edit',
        icon: FileText,
    },
];

const mountedRoots: Root[] = [];

async function render(search: CommandPaletteSearch): Promise<void> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <CommandPalette
                    open
                    onOpenChange={() => {}}
                    items={navigation}
                    search={search}
                />
            </I18nProvider>,
        );
    });
}

function input(): HTMLInputElement {
    return document.querySelector<HTMLInputElement>('[role="combobox"]')!;
}

async function type(value: string): Promise<void> {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype,
            'value',
        )!.set!.call(input(), value);
        input().dispatchEvent(new Event('input', { bubbles: true }));
    });
}

async function waitForSearch(): Promise<void> {
    await act(async () => {
        await vi.advanceTimersByTimeAsync(250);
    });
}

function optionLabels(): string[] {
    return Array.from(document.querySelectorAll('[role="option"]')).map(
        (option) => option.textContent ?? '',
    );
}

const labels = { loading: 'Searching…', error: 'Search is unavailable.' };

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    for (const root of mountedRoots.splice(0)) {
        act(() => root.unmount());
    }
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe('CommandPalette search', () => {
    it('loads records after the delay and lists them after local items', async () => {
        const load = vi.fn().mockResolvedValue(found);
        await render({ load, labels });

        await type('b');
        await waitForSearch();
        expect(load).not.toHaveBeenCalled();

        await type('bakery');
        await waitForSearch();

        expect(load).toHaveBeenCalledTimes(1);
        expect(load.mock.calls[0][0]).toBe('bakery');
        expect(optionLabels()).toEqual(['Bakery offerEN · offer']);
        expect(document.body.textContent).toContain('Pages');
    });

    it('keeps local items and announces an error when loading fails', async () => {
        const load = vi.fn().mockRejectedValue(new Error('500'));
        await render({ load, labels });

        await type('da');
        await waitForSearch();

        expect(optionLabels()).toEqual(['Dashboard']);
        expect(document.querySelector('[role="status"]')?.textContent).toBe(
            'Search is unavailable.',
        );
    });

    it('aborts the previous request when the query changes', async () => {
        const signals: AbortSignal[] = [];
        const load = vi.fn((_query: string, signal: AbortSignal) => {
            signals.push(signal);

            return new Promise<CommandPaletteItem[]>(() => {});
        });
        await render({ load, labels });

        await type('ba');
        await waitForSearch();
        await type('bak');
        await waitForSearch();

        expect(load).toHaveBeenCalledTimes(2);
        expect(signals[0].aborted).toBe(true);
        expect(signals[1].aborted).toBe(false);
    });
});
