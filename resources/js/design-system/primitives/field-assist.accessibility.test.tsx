import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { I18nProvider } from '@/i18n';
import type { FieldAssist, FieldAssistResult } from './field-assist';
import { TextareaField } from './textarea-field';
import { TextField } from './text-field';

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

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

function assist(result: FieldAssistResult): FieldAssist & {
    request: ReturnType<typeof vi.fn>;
} {
    return {
        labels: {
            request: 'Suggest',
            preview: 'Suggestion',
            apply: 'Use',
            discard: 'Discard',
        },
        request: vi.fn(async () => result),
    };
}

function button(container: HTMLElement, text: string): HTMLButtonElement {
    const found = Array.from(container.querySelectorAll('button')).find(
        (element) => element.textContent?.trim() === text,
    );

    if (!found) {
        throw new Error(`Button "${text}" not found`);
    }

    return found;
}

async function click(element: HTMLElement) {
    await act(async () => {
        element.click();
    });
}

const fields = [
    {
        name: 'TextField',
        render: (props: {
            onChange: (value: string) => void;
            assist: FieldAssist;
            readOnly?: boolean;
        }) =>
            props.readOnly ? (
                <TextField
                    name="slug"
                    label="Slug"
                    value="old"
                    readOnly
                    assist={props.assist}
                />
            ) : (
                <TextField
                    name="slug"
                    label="Slug"
                    value="old"
                    onChange={props.onChange}
                    assist={props.assist}
                />
            ),
        control: 'input',
    },
    {
        name: 'TextareaField',
        render: (props: {
            onChange: (value: string) => void;
            assist: FieldAssist;
            readOnly?: boolean;
        }) =>
            props.readOnly ? (
                <TextareaField
                    name="meta"
                    label="Meta"
                    value="old"
                    readOnly
                    assist={props.assist}
                />
            ) : (
                <TextareaField
                    name="meta"
                    label="Meta"
                    value="old"
                    onChange={props.onChange}
                    assist={props.assist}
                />
            ),
        control: 'textarea',
    },
] as const;

describe.each(fields)('$name assist', ({ render: field, control }) => {
    it('previews the suggestion and applies it only after "Use"', async () => {
        const onChange = vi.fn();
        const helper = assist({ suggestion: 'New value' });
        const container = await render(field({ onChange, assist: helper }));

        await click(button(container, 'Suggest'));

        expect(helper.request).toHaveBeenCalledTimes(1);
        expect(container.textContent).toContain('New value');
        expect(onChange).not.toHaveBeenCalled();

        await click(button(container, 'Use'));

        expect(onChange).toHaveBeenCalledWith('New value');
        expect(container.textContent).not.toContain('New value');
        expect(document.activeElement).toBe(container.querySelector(control));
    });

    it('discards the suggestion without changing the value', async () => {
        const onChange = vi.fn();
        const container = await render(
            field({ onChange, assist: assist({ suggestion: 'New value' }) }),
        );

        await click(button(container, 'Suggest'));
        await click(button(container, 'Discard'));

        expect(onChange).not.toHaveBeenCalled();
        expect(container.textContent).not.toContain('New value');
        expect(document.activeElement).toBe(container.querySelector(control));
    });

    it('announces a failed request at the field', async () => {
        const onChange = vi.fn();
        const container = await render(
            field({
                onChange,
                assist: assist({ error: 'Assistant is down.' }),
            }),
        );

        await click(button(container, 'Suggest'));

        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'Assistant is down.',
        );
        expect(onChange).not.toHaveBeenCalled();
    });

    it('offers no suggestion on a read-only field', async () => {
        const helper = assist({ suggestion: 'New value' });
        const container = await render(
            field({ onChange: vi.fn(), assist: helper, readOnly: true }),
        );

        expect(container.querySelector('button')).toBeNull();
        expect(helper.request).not.toHaveBeenCalled();
    });
});

function rewriteAssist(): FieldAssist & { request: ReturnType<typeof vi.fn> } {
    return {
        labels: {
            request: 'Improve text',
            preview: 'Improved text',
            apply: 'Replace',
            discard: 'Cancel',
        },
        modes: [
            { value: 'fix', label: 'Fix mistakes' },
            { value: 'shorten', label: 'Shorten' },
        ],
        request: vi.fn(async (mode?: string) => ({
            suggestion: `Text (${mode})`,
        })),
    };
}

async function openMenu(trigger: HTMLButtonElement) {
    await act(async () => {
        trigger.dispatchEvent(
            new MouseEvent('pointerdown', { bubbles: true, button: 0 }),
        );
    });
    await act(async () => {
        await import('./field-assist-menu');
        await new Promise((resolve) => setTimeout(resolve, 0));
    });
}

describe('assist with modes', () => {
    it('picks a mode from a menu and previews the result before replacing', async () => {
        const onChange = vi.fn();
        const helper = rewriteAssist();
        const container = await render(
            <TextareaField
                name="excerpt"
                label="Excerpt"
                value="old"
                onChange={onChange}
                assist={helper}
            />,
        );
        const trigger = button(container, 'Improve text');

        expect(trigger.getAttribute('aria-haspopup')).toBe('menu');
        expect(helper.request).not.toHaveBeenCalled();

        await openMenu(trigger);
        const items = Array.from(
            document.querySelectorAll<HTMLElement>('[role="menuitem"]'),
        );

        expect(items.map((item) => item.textContent)).toEqual([
            'Fix mistakes',
            'Shorten',
        ]);

        await click(items[1]);

        expect(helper.request).toHaveBeenCalledWith('shorten');
        expect(container.textContent).toContain('Improved text');
        expect(container.textContent).toContain('Text (shorten)');
        expect(onChange).not.toHaveBeenCalled();

        await click(button(container, 'Replace'));

        expect(onChange).toHaveBeenCalledWith('Text (shorten)');
    });

    it('offers several helpers on one field with one shared preview', async () => {
        const onChange = vi.fn();
        const suggest = assist({ suggestion: 'Suggested value' });
        const rewrite = rewriteAssist();
        const container = await render(
            <TextField
                name="meta"
                label="Meta"
                value="old"
                onChange={onChange}
                assist={[suggest, rewrite]}
            />,
        );

        expect(button(container, 'Improve text')).toBeTruthy();

        await click(button(container, 'Suggest'));

        expect(suggest.request).toHaveBeenCalledTimes(1);
        expect(container.textContent).toContain('Suggestion');
        expect(container.textContent).toContain('Suggested value');

        await click(button(container, 'Use'));

        expect(onChange).toHaveBeenCalledWith('Suggested value');
        expect(rewrite.request).not.toHaveBeenCalled();
    });

    it('offers no helper on a read-only field', async () => {
        const container = await render(
            <TextField
                name="meta"
                label="Meta"
                value="old"
                readOnly
                assist={[assist({ suggestion: 'x' }), rewriteAssist()]}
            />,
        );

        expect(container.querySelector('button')).toBeNull();
    });
});
