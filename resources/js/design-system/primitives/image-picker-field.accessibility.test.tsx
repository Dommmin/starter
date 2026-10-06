import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import {
    ImagePickerField,
    type ImagePickerFieldProps,
} from './image-picker-field';
import type { RichTextImagePicker } from './rich-text-document';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

function picker(): RichTextImagePicker {
    return {
        load: vi.fn(async () => [
            {
                id: 7,
                name: 'red.jpg',
                alt: 'A red square',
                thumbnailUrl: '/storage/media/u/red-320.jpg',
                width: 640,
                height: 360,
            },
        ]),
        previewUrl: (mediaId) => `/admin/media/${mediaId}/preview`,
        labels: {
            image: 'Insert image',
            dialogTitle: 'Choose image',
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
            submit: 'Use image',
            cancel: 'Cancel',
            close: 'Close',
        },
    };
}

function props(
    overrides: Partial<ImagePickerFieldProps> = {},
): ImagePickerFieldProps {
    return {
        id: 'cover-field',
        name: 'cover_media_id',
        label: 'Cover image',
        hint: 'From the media library',
        value: '',
        onChange: () => {},
        picker: picker(),
        labels: {
            choose: 'Choose image',
            change: 'Change image',
            remove: 'Remove image',
            empty: 'No cover image',
            preview: 'Selected cover',
        },
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

    return container;
}

function button(container: HTMLElement, name: string): HTMLButtonElement {
    return Array.from(container.querySelectorAll('button')).find(
        (element) => element.textContent?.trim() === name,
    ) as HTMLButtonElement;
}

async function waitForDialogGrid(): Promise<HTMLElement> {
    for (let attempt = 0; attempt < 100; attempt++) {
        const dialog = document.querySelector<HTMLElement>('[role="dialog"]');

        if (dialog?.querySelector('input[type="radio"]')) {
            return dialog;
        }

        await act(async () => {
            await new Promise((resolve) => setTimeout(resolve, 10));
        });
    }

    throw new Error('Image picker did not render.');
}

beforeAll(async () => {
    // Warm the lazily imported picker dialog so the interaction test measures
    // the field, not the cold module transform of the first dynamic import.
    // The transform can outlast the default hook timeout when the pre-commit
    // checks run in parallel, so it gets an explicit budget.
    await import('./rich-text-image-dialog');
}, 30_000);

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.innerHTML = '';
});

describe('ImagePickerField', () => {
    it('is a labelled, focusable group that announces hint and error', async () => {
        const container = await render(
            <ImagePickerField
                {...props({ error: 'Choose a clean image.', required: true })}
            />,
        );
        const group = container.querySelector<HTMLElement>('#cover-field')!;

        expect(group.getAttribute('role')).toBe('group');
        expect(group.tabIndex).toBe(-1);
        const labelId = group.getAttribute('aria-labelledby')!;
        expect(document.getElementById(labelId)?.textContent).toContain(
            'Cover image',
        );
        const describedBy = group.getAttribute('aria-describedby')!.split(' ');
        expect(
            describedBy.map((id) => document.getElementById(id)?.textContent),
        ).toEqual(['From the media library', 'Choose a clean image.']);
        expect(container.textContent).toContain('No cover image');
        expect(button(container, 'Remove image')).toBeUndefined();
        expect(
            container.querySelector<HTMLInputElement>(
                'input[name="cover_media_id"]',
            )?.value,
        ).toBe('');
    });

    it('previews the selected image and clears it', async () => {
        const onChange = vi.fn();
        const container = await render(
            <ImagePickerField {...props({ value: '12', onChange })} />,
        );

        const preview = container.querySelector('img')!;
        expect(preview.getAttribute('src')).toBe('/admin/media/12/preview');
        expect(preview.getAttribute('alt')).toBe('Selected cover');
        expect(button(container, 'Change image')).toBeDefined();

        await act(async () => button(container, 'Remove image').click());
        expect(onChange).toHaveBeenCalledWith('');
    });

    it('selects a DAM image without asking for alternative text', async () => {
        const onChange = vi.fn();
        const imagePicker = picker();
        const container = await render(
            <ImagePickerField {...props({ onChange, picker: imagePicker })} />,
        );

        expect(document.querySelector('[role="dialog"]')).toBeNull();
        await act(async () => button(container, 'Choose image').click());
        let dialog = await waitForDialogGrid();
        expect(imagePicker.load).toHaveBeenCalledWith('');
        expect(
            dialog.querySelector('input[name="cover_media_id-image-alt"]'),
        ).toBeNull();

        await act(async () => {
            dialog
                .querySelector<HTMLInputElement>('input[type="radio"]')!
                .click();
        });
        dialog = document.querySelector<HTMLElement>('[role="dialog"]')!;
        await act(async () => {
            dialog.querySelector('form')!.requestSubmit();
        });

        expect(onChange).toHaveBeenCalledWith('7');
        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });

    it('disables its actions while the form is pending', async () => {
        const container = await render(
            <ImagePickerField {...props({ value: '3', disabled: true })} />,
        );

        expect(button(container, 'Change image').disabled).toBe(true);
        expect(button(container, 'Remove image').disabled).toBe(true);
    });
});
