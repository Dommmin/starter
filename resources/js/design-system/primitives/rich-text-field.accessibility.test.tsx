import type { Editor } from '@tiptap/react';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { renderToString } from 'react-dom/server';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { ResourceForm, type ResourceFormSection } from './resource-form';
import {
    isAllowedRichTextHref,
    RichTextField,
    type RichTextDocument,
    type RichTextFieldLabels,
    type RichTextFieldProps,
    type RichTextImageLabels,
    type RichTextImagePicker,
} from './rich-text-field';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const labels: RichTextFieldLabels = {
    toolbar: 'Formatting',
    heading2: 'Heading 2',
    heading3: 'Heading 3',
    heading4: 'Heading 4',
    bold: 'Bold',
    italic: 'Italic',
    strike: 'Strikethrough',
    code: 'Inline code',
    bulletList: 'Bulleted list',
    orderedList: 'Numbered list',
    blockquote: 'Quote',
    horizontalRule: 'Divider',
    link: 'Add link',
    unlink: 'Remove link',
    linkDialogTitle: 'Link',
    linkUrlLabel: 'URL',
    linkUrlHint: 'http, https or mailto',
    linkSubmit: 'Apply',
    linkCancel: 'Cancel',
    linkClose: 'Close',
    linkInvalid: 'Enter a valid http, https or mailto address.',
};

const helloDocument: RichTextDocument = {
    type: 'doc',
    content: [
        { type: 'paragraph', content: [{ type: 'text', text: 'Hello' }] },
    ],
};

const mountedRoots: Root[] = [];

function props(
    overrides: Partial<RichTextFieldProps> = {},
): RichTextFieldProps {
    return {
        id: 'body',
        name: 'body',
        label: 'Body',
        value: helloDocument,
        onChange: () => {},
        labels,
        ...overrides,
    };
}

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(node);
    });
    await waitForEditor(container);

    return container;
}

beforeAll(async () => {
    // Warm the lazily imported editor and picker dialog chunks so the tests
    // measure the field, not the cold module transform of the first dynamic
    // import under a loaded test runner (it can exceed the polling budget).
    await Promise.all([
        import('./rich-text-editor'),
        import('./rich-text-image-dialog'),
    ]);
});

/** The editor chunk is lazy-loaded after mount; wait until Tiptap renders. */
async function waitForEditor(container: HTMLElement): Promise<void> {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (container.querySelector('[role="textbox"]')) {
            return;
        }

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 10));
        });
    }

    throw new Error('Rich text editor did not render.');
}

function editorSurface(container: HTMLElement): HTMLElement {
    return container.querySelector<HTMLElement>('[role="textbox"]')!;
}

function editorOf(container: HTMLElement): Editor {
    return (editorSurface(container) as HTMLElement & { editor: Editor })
        .editor;
}

function button(container: HTMLElement, name: string): HTMLButtonElement {
    return container.querySelector<HTMLButtonElement>(
        `button[aria-label="${name}"]`,
    )!;
}

function textOf(ids: string | null): string {
    return (ids ?? '')
        .split(' ')
        .map((id) => document.getElementById(id)?.textContent ?? '')
        .join(' ');
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('RichTextField', () => {
    it('renders a skeleton instead of the editor during SSR', () => {
        const html = renderToString(<RichTextField {...props()} />);

        expect(html).toContain('Body');
        expect(html).not.toContain('contenteditable');
        expect(html).toContain('animate-pulse');
    });

    it('labels and describes the editable surface and marks errors', async () => {
        const container = await render(
            <RichTextField
                {...props({
                    hint: 'Shown on the public page',
                    error: 'Body is required.',
                    required: true,
                })}
            />,
        );
        const surface = editorSurface(container);

        expect(surface.id).toBe('body');
        expect(surface.getAttribute('contenteditable')).toBe('true');
        expect(surface.getAttribute('aria-multiline')).toBe('true');
        expect(textOf(surface.getAttribute('aria-labelledby'))).toContain(
            'Body',
        );
        const description = textOf(surface.getAttribute('aria-describedby'));
        expect(description).toContain('Shown on the public page');
        expect(description).toContain('Body is required.');
        expect(surface.getAttribute('aria-invalid')).toBe('true');
        expect(surface.getAttribute('aria-required')).toBe('true');
        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'Body is required.',
        );
    });

    it('exposes a named toolbar with toggle buttons and one tab stop', async () => {
        const container = await render(<RichTextField {...props()} />);
        const toolbar = container.querySelector('[role="toolbar"]')!;

        expect(toolbar.getAttribute('aria-label')).toBe('Formatting');
        expect(toolbar.getAttribute('aria-controls')).toBe('body');

        const buttons = Array.from(
            toolbar.querySelectorAll<HTMLButtonElement>('button'),
        );
        buttons.forEach((item) =>
            expect(item.getAttribute('aria-label')).toBeTruthy(),
        );
        expect(button(container, 'Bold').getAttribute('aria-pressed')).toBe(
            'false',
        );
        expect(
            button(container, 'Bold').getAttribute('aria-keyshortcuts'),
        ).toContain('Control+B');
        expect(button(container, 'Divider').hasAttribute('aria-pressed')).toBe(
            false,
        );
        expect(button(container, 'Remove link').disabled).toBe(true);
        expect(
            buttons
                .filter((item) => item.tabIndex === 0)
                .map((item) => item.getAttribute('aria-label')),
        ).toEqual(['Heading 2']);

        await act(async () => {
            buttons[0]?.focus();
            toolbar.dispatchEvent(
                new KeyboardEvent('keydown', {
                    key: 'ArrowRight',
                    bubbles: true,
                }),
            );
        });
        expect(document.activeElement).toBe(button(container, 'Heading 3'));
        expect(button(container, 'Heading 3').tabIndex).toBe(0);

        await act(async () => {
            toolbar.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'End', bubbles: true }),
            );
        });
        expect(document.activeElement).toBe(button(container, 'Add link'));
    });

    it('applies toolbar commands, reflects aria-pressed and reports the document', async () => {
        const onChange = vi.fn();
        const container = await render(
            <RichTextField {...props({ onChange })} />,
        );

        await act(async () => {
            button(container, 'Heading 2').click();
        });

        expect(onChange).toHaveBeenLastCalledWith({
            type: 'doc',
            content: [
                {
                    type: 'heading',
                    attrs: { level: 2 },
                    content: [{ type: 'text', text: 'Hello' }],
                },
                // Tiptap TrailingNode keeps a paragraph after a trailing block.
                { type: 'paragraph' },
            ],
        });
        expect(
            button(container, 'Heading 2').getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('reports typed content through onChange', async () => {
        const onChange = vi.fn();
        const container = await render(
            <RichTextField {...props({ onChange, value: { type: 'doc' } })} />,
        );

        await act(async () => {
            editorOf(container).commands.insertContent('World');
        });

        expect(onChange).toHaveBeenLastCalledWith({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'World' }],
                },
            ],
        });
    });

    it('keeps the schema closed: no level-1 headings, images or code blocks', async () => {
        const container = await render(<RichTextField {...props()} />);
        const editor = editorOf(container);

        await act(async () => {
            editor.commands.setContent(
                '<h1>Title</h1><img src="https://example.test/a.png"><pre><code>x</code></pre><p><a href="javascript:alert(1)">bad</a> <a href="https://example.test">ok</a></p>',
            );
        });

        const json = JSON.stringify(editor.getJSON());
        expect(json).not.toContain('"level":1');
        expect(json).not.toContain('image');
        expect(json).not.toContain('codeBlock');
        expect(json).not.toContain('javascript:');
        expect(json).toContain('https://example.test');
    });

    it('applies external value changes without echoing onChange', async () => {
        const onChange = vi.fn();
        const container = document.createElement('div');
        document.body.append(container);
        const root = createRoot(container);
        mountedRoots.push(root);

        await act(async () => {
            root.render(<RichTextField {...props({ onChange })} />);
        });
        await waitForEditor(container);
        await act(async () => {
            root.render(
                <RichTextField
                    {...props({
                        onChange,
                        value: {
                            type: 'doc',
                            content: [
                                {
                                    type: 'paragraph',
                                    content: [{ type: 'text', text: 'Reset' }],
                                },
                            ],
                        },
                    })}
                />,
            );
        });

        expect(editorSurface(container).textContent).toBe('Reset');
        expect(onChange).not.toHaveBeenCalled();
    });

    it('disables the toolbar and the editable surface', async () => {
        const container = await render(
            <RichTextField {...props({ disabled: true })} />,
        );

        expect(editorSurface(container).getAttribute('contenteditable')).toBe(
            'false',
        );
        expect(editorSurface(container).getAttribute('aria-disabled')).toBe(
            'true',
        );
        expect(button(container, 'Bold').disabled).toBe(true);
    });

    it('rejects links outside http(s)/mailto', () => {
        expect(isAllowedRichTextHref('https://example.test')).toBe(true);
        expect(isAllowedRichTextHref('mailto:team@example.test')).toBe(true);
        expect(isAllowedRichTextHref('javascript:alert(1)')).toBe(false);
        expect(isAllowedRichTextHref('/relative')).toBe(false);
    });
});

describe('RichTextField link dialog', () => {
    async function openDialog(container: HTMLElement) {
        await act(async () => {
            editorOf(container).commands.selectAll();
        });
        await act(async () => {
            button(container, 'Add link').click();
        });

        return document.querySelector<HTMLElement>('[role="dialog"]')!;
    }

    async function typeUrl(dialog: HTMLElement, url: string) {
        const input = dialog.querySelector<HTMLInputElement>('input')!;
        await act(async () => {
            Object.getOwnPropertyDescriptor(
                HTMLInputElement.prototype,
                'value',
            )!.set!.call(input, url);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await act(async () => {
            dialog.querySelector('form')!.requestSubmit();
        });
    }

    it('validates the URL, sets the link and never submits the enclosing form', async () => {
        type Values = { body: RichTextDocument };
        const sections: ResourceFormSection<Values>[] = [
            {
                id: 'content',
                title: 'Content',
                fields: [
                    { type: 'richText', name: 'body', label: 'Body', labels },
                ],
            },
        ];
        const onChange = vi.fn();
        const onSubmit = vi.fn();
        const container = await render(
            <ResourceForm<Values>
                sections={sections}
                values={{ body: helloDocument }}
                onChange={onChange}
                onSubmit={onSubmit}
                labels={{ submit: 'Save', errorSummaryTitle: 'Fix' }}
            />,
        );

        const dialog = await openDialog(container);
        expect(dialog.textContent).toContain('Link');

        await typeUrl(dialog, 'ftp://example.test');
        expect(dialog.textContent).toContain(labels.linkInvalid);
        expect(onChange).not.toHaveBeenCalled();

        await typeUrl(dialog, 'https://example.test/docs');
        expect(document.querySelector('[role="dialog"]')).toBeNull();
        expect(onSubmit).not.toHaveBeenCalled();
        expect(JSON.stringify(onChange.mock.lastCall)).toContain(
            'https://example.test/docs',
        );
        expect(onChange.mock.lastCall?.[0]).toBe('body');
    });
});

describe('ResourceForm richText field', () => {
    it('links the error summary to the editor surface', async () => {
        type Values = { title: string; body: RichTextDocument };
        const sections: ResourceFormSection<Values>[] = [
            {
                id: 'content',
                title: 'Content',
                fields: [
                    { type: 'text', name: 'title', label: 'Title' },
                    {
                        type: 'richText',
                        name: 'body',
                        label: 'Body',
                        hint: 'Main content',
                        labels,
                    },
                ],
            },
        ];
        const container = await render(
            <ResourceForm<Values>
                sections={sections}
                values={{ title: 'A', body: helloDocument }}
                errors={{ body: 'Body is required.' }}
                onChange={() => {}}
                onSubmit={() => {}}
                labels={{ submit: 'Save', errorSummaryTitle: 'Fix' }}
            />,
        );

        const surface = editorSurface(container);
        const summaryLink = container.querySelector<HTMLAnchorElement>(
            `a[href="#${surface.id}"]`,
        );
        expect(summaryLink?.textContent).toContain('Body is required.');
        expect(surface.getAttribute('aria-invalid')).toBe('true');
        expect(
            container.querySelector<HTMLInputElement>('input[name="body"]')
                ?.value,
        ).toBe(JSON.stringify(helloDocument));
    });
});

describe('RichTextField image picker', () => {
    const imageLabels: RichTextImageLabels = {
        image: 'Insert image',
        dialogTitle: 'Insert image',
        dialogDescription: 'Clean images only',
        searchLabel: 'Search images',
        searchPlaceholder: 'Name',
        searchClear: 'Clear',
        listLabel: 'Available images',
        loading: 'Loading images',
        empty: 'No images',
        error: 'Could not load images',
        retry: 'Retry',
        selectRequired: 'Select an image.',
        altLabel: 'Alternative text',
        altHint: 'Describe it',
        submit: 'Insert',
        cancel: 'Cancel',
        close: 'Close',
    };

    function picker(
        load: RichTextImagePicker['load'] = async () => [
            {
                id: 7,
                name: 'red.jpg',
                alt: 'A red square',
                thumbnailUrl: '/storage/media/u/red-320.jpg',
                width: 640,
                height: 360,
            },
        ],
    ): RichTextImagePicker {
        return {
            load: vi.fn(load),
            previewUrl: (mediaId) => `/admin/media/${mediaId}/preview`,
            labels: imageLabels,
        };
    }

    async function waitForDialogGrid(): Promise<HTMLElement> {
        for (let attempt = 0; attempt < 100; attempt++) {
            const dialog =
                document.querySelector<HTMLElement>('[role="dialog"]');

            if (dialog?.querySelector('input[type="radio"]')) {
                return dialog;
            }

            await act(async () => {
                await new Promise((resolve) => setTimeout(resolve, 10));
            });
        }

        throw new Error('Image picker did not render.');
    }

    it('shows the image button only with a picker', async () => {
        const container = await render(<RichTextField {...props()} />);

        expect(button(container, 'Insert image')).toBeNull();
    });

    it('inserts a DAM reference with alt text and previews it in the editor', async () => {
        const onChange = vi.fn();
        const imagePicker = picker();
        const container = await render(
            <RichTextField {...props({ onChange, imagePicker })} />,
        );

        const trigger = button(container, 'Insert image');
        expect(trigger.getAttribute('aria-haspopup')).toBe('dialog');

        await act(async () => trigger.click());
        let dialog = await waitForDialogGrid();
        expect(imagePicker.load).toHaveBeenCalledWith('');

        await act(async () => {
            dialog.querySelector('form')!.requestSubmit();
        });
        expect(dialog.textContent).toContain('Select an image.');

        await act(async () => {
            dialog
                .querySelector<HTMLInputElement>('input[type="radio"]')!
                .click();
        });
        dialog = document.querySelector<HTMLElement>('[role="dialog"]')!;
        const alt = Array.from(
            dialog.querySelectorAll<HTMLInputElement>('input'),
        ).find((input) => input.name === 'body-image-alt')!;
        expect(alt.value).toBe('A red square');

        await act(async () => {
            dialog.querySelector('form')!.requestSubmit();
        });

        expect(document.querySelector('[role="dialog"]')).toBeNull();
        const inserted = JSON.stringify(onChange.mock.lastCall?.[0]);
        expect(inserted).toContain(
            '{"type":"image","attrs":{"mediaId":7,"alt":"A red square"}}',
        );
        expect(inserted).not.toContain('src');

        const preview = editorSurface(container).querySelector('img')!;
        expect(preview.getAttribute('src')).toBe('/admin/media/7/preview');
        expect(preview.getAttribute('data-media-id')).toBe('7');
        expect(preview.getAttribute('alt')).toBe('A red square');
    });

    it('keeps only the media id and alt of pasted DAM images', async () => {
        const container = await render(
            <RichTextField {...props({ imagePicker: picker() })} />,
        );
        const editor = editorOf(container);

        await act(async () => {
            editor.commands.setContent(
                '<img data-media-id="3" alt="Kept" src="https://evil.test/x.png" onerror="alert(1)"><img src="https://evil.test/y.png">',
            );
        });

        const images = (editor.getJSON().content ?? []).filter(
            (node) => node.type === 'image',
        );
        expect(images).toEqual([
            { type: 'image', attrs: { mediaId: 3, alt: 'Kept' } },
        ]);
    });

    it('offers a retry when images cannot be loaded', async () => {
        const imagePicker = picker(async () => {
            throw new Error('offline');
        });
        const container = await render(
            <RichTextField {...props({ imagePicker })} />,
        );

        await act(async () => button(container, 'Insert image').click());

        for (let attempt = 0; attempt < 50; attempt++) {
            if (document.body.textContent?.includes('Could not load images')) {
                break;
            }

            await act(async () => {
                await new Promise((resolve) => setTimeout(resolve, 10));
            });
        }

        expect(document.body.textContent).toContain('Could not load images');
        const retry = Array.from(
            document.querySelectorAll<HTMLButtonElement>(
                '[role="dialog"] button',
            ),
        ).find((candidate) => candidate.textContent === 'Retry')!;
        await act(async () => retry.click());
        expect(imagePicker.load).toHaveBeenCalledTimes(2);
    });
});
