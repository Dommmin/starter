import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { AiSettingsPanel } from '@/components/admin/ai-settings-panel';
import { I18nProvider } from '@/i18n';

vi.mock('@inertiajs/react', () => ({
    router: { delete: vi.fn(), on: () => () => {} },
    usePage: () => ({ props: {} }),
    useForm: <T extends Record<string, unknown>>(initial: T) => ({
        data: initial,
        errors: {},
        processing: false,
        setData: vi.fn(),
        reset: vi.fn(),
        submit: vi.fn(),
    }),
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

const roots: Root[] = [];

async function render(ai: App.Data.Admin.System.AiSettingsData) {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    roots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AiSettingsPanel ai={ai} />
            </I18nProvider>,
        );
    });

    return container;
}

function buttonLabels(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('button')).map(
        (button) => button.textContent ?? '',
    );
}

afterEach(async () => {
    await act(async () => roots.splice(0).forEach((root) => root.unmount()));
    document.body.innerHTML = '';
});

describe('AiSettingsPanel', () => {
    it('requires a key and offers no removal when the panel stores none', async () => {
        const container = await render({
            provider: null,
            hasPanelKey: false,
            environmentConfigured: false,
            assistantEnabled: false,
        });
        const keyInput = container.querySelector<HTMLInputElement>(
            'input[name="api_key"]',
        );

        expect(keyInput?.required).toBe(true);
        expect(keyInput?.value).toBe('');
        expect(container.textContent).toContain('admin.ai.statusMissing');
        expect(container.textContent).toContain('admin.ai.assistantDisabled');
        expect(buttonLabels(container)).not.toContain('admin.ai.remove');
    });

    it('keeps the stored key optional and offers removal when the panel stores one', async () => {
        const container = await render({
            provider: 'openai',
            hasPanelKey: true,
            environmentConfigured: true,
            assistantEnabled: true,
        });
        const keyInput = container.querySelector<HTMLInputElement>(
            'input[name="api_key"]',
        );

        expect(keyInput?.required).toBe(false);
        expect(keyInput?.type).toBe('password');
        expect(container.textContent).toContain('admin.ai.statusPanel');
        expect(container.textContent).not.toContain(
            'admin.ai.assistantDisabled',
        );
        expect(buttonLabels(container)).toContain('admin.ai.remove');
    });

    it('shows the server configuration as the key source when only the environment has one', async () => {
        const container = await render({
            provider: null,
            hasPanelKey: false,
            environmentConfigured: true,
            assistantEnabled: true,
        });

        expect(container.textContent).toContain('admin.ai.statusEnvironment');
    });
});
