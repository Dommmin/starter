import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FileDropzone, UploadQueue } from './file-dropzone';
import { Image } from './image';
import { MediaGrid, MediaThumbnail } from './media-grid';

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

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

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('Image', () => {
    it('renders a lazy picture with sources, intrinsic size and alt', async () => {
        const container = await render(
            <Image
                src="/a-640.jpg"
                srcset="/a-320.jpg 320w, /a-640.jpg 640w"
                sources={[
                    { type: 'image/avif', srcset: '/a-320.avif 320w' },
                    { type: 'image/webp', srcset: '/a-320.webp 320w' },
                ]}
                width={640}
                height={360}
                sizes="100vw"
                alt="A red square"
            />,
        );

        const sources = container.querySelectorAll('picture > source');
        expect(
            Array.from(sources).map((source) => source.getAttribute('type')),
        ).toEqual(['image/avif', 'image/webp']);
        expect(sources[0]?.getAttribute('sizes')).toBe('100vw');

        const img = container.querySelector('picture > img')!;
        expect(img.getAttribute('alt')).toBe('A red square');
        expect(img.getAttribute('width')).toBe('640');
        expect(img.getAttribute('height')).toBe('360');
        expect(img.getAttribute('loading')).toBe('lazy');
        expect(img.getAttribute('decoding')).toBe('async');
        expect(img.getAttribute('fetchpriority')).toBeNull();
        expect(img.getAttribute('srcset')).toBe(
            '/a-320.jpg 320w, /a-640.jpg 640w',
        );
    });

    it('crops an image into a full-width 16:9 frame', async () => {
        const container = await render(
            <Image
                src="/square.jpg"
                width={1200}
                height={1200}
                alt="A square cover"
                frame="wide"
            />,
        );

        const frame = container.firstElementChild!;
        const img = frame.querySelector('img')!;
        expect(frame.classList.contains('aspect-video')).toBe(true);
        expect(img.classList.contains('object-cover')).toBe(true);
        expect(img.getAttribute('alt')).toBe('A square cover');
    });

    it('loads the LCP image eagerly with high priority', async () => {
        const container = await render(
            <Image
                src="/hero.jpg"
                width={1920}
                height={1080}
                alt=""
                priority
            />,
        );

        const img = container.querySelector('img')!;
        expect(container.querySelector('picture')).toBeNull();
        expect(img.getAttribute('loading')).toBe('eager');
        expect(img.getAttribute('fetchpriority')).toBe('high');
        expect(img.getAttribute('decoding')).toBeNull();
        expect(img.getAttribute('alt')).toBe('');
    });
});

describe('FileDropzone', () => {
    it('exposes a labelled group whose button opens the native chooser', async () => {
        const onFilesSelected = vi.fn();
        const container = await render(
            <FileDropzone
                label="Upload files"
                hint="JPEG or PDF, up to 50 MB"
                chooseLabel="Choose files"
                accept=".jpg,.pdf"
                multiple
                onFilesSelected={onFilesSelected}
            />,
        );

        const group = container.querySelector('[role="group"]')!;
        const labelledBy = group.getAttribute('aria-labelledby')!;
        const describedBy = group.getAttribute('aria-describedby')!;
        expect(document.getElementById(labelledBy)?.textContent).toBe(
            'Upload files',
        );
        expect(document.getElementById(describedBy)?.textContent).toBe(
            'JPEG or PDF, up to 50 MB',
        );

        const input =
            container.querySelector<HTMLInputElement>('input[type="file"]')!;
        expect(input.accept).toBe('.jpg,.pdf');
        expect(input.multiple).toBe(true);
        expect(input.tabIndex).toBe(-1);

        const click = vi.spyOn(input, 'click');
        const button = container.querySelector('button')!;
        expect(button.textContent).toBe('Choose files');
        await act(async () => button.click());
        expect(click).toHaveBeenCalled();
    });

    it('passes dropped files to the caller', async () => {
        const onFilesSelected = vi.fn();
        const container = await render(
            <FileDropzone
                label="Upload files"
                chooseLabel="Choose files"
                multiple
                onFilesSelected={onFilesSelected}
            />,
        );

        const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' });
        const drop = new Event('drop', { bubbles: true, cancelable: true });
        Object.defineProperty(drop, 'dataTransfer', {
            value: { files: [file] },
        });

        await act(async () => {
            container.querySelector('[role="group"]')!.dispatchEvent(drop);
        });

        expect(onFilesSelected).toHaveBeenCalledWith([file]);
    });
});

describe('UploadQueue', () => {
    it('shows progress, per-file errors in a live region and dismiss controls', async () => {
        const onDismiss = vi.fn();
        const container = await render(
            <UploadQueue
                label="Uploads"
                items={[
                    {
                        id: 'a',
                        name: 'a.jpg',
                        status: 'uploading',
                        progress: 40,
                        message: 'Uploading… 40%',
                    },
                    {
                        id: 'b',
                        name: 'b.php',
                        status: 'error',
                        message: 'This file type is not allowed.',
                    },
                ]}
                dismissLabel={(item) => `Remove ${item.name}`}
                onDismiss={onDismiss}
            />,
        );

        expect(container.querySelector('ul')?.getAttribute('aria-label')).toBe(
            'Uploads',
        );
        const progress = container.querySelector('[role="progressbar"]')!;
        expect(progress.getAttribute('aria-valuenow')).toBe('40');
        expect(
            Array.from(container.querySelectorAll('[aria-live="polite"]')).map(
                (node) => node.textContent,
            ),
        ).toEqual(['Uploading… 40%', 'This file type is not allowed.']);

        const dismiss = container.querySelector<HTMLButtonElement>(
            'button[aria-label="Remove b.php"]',
        )!;
        expect(
            container.querySelector('button[aria-label="Remove a.jpg"]'),
        ).toBeNull();
        await act(async () => dismiss.click());
        expect(onDismiss).toHaveBeenCalledWith('b');
    });

    it('renders nothing without items', async () => {
        const container = await render(
            <UploadQueue label="Uploads" items={[]} dismissLabel={() => ''} />,
        );

        expect(container.innerHTML).toBe('');
    });
});

describe('MediaGrid', () => {
    const items = [
        {
            id: 1,
            name: 'one.jpg',
            thumbnailUrl: '/one-320.jpg',
            width: 640,
            height: 360,
        },
        {
            id: 2,
            name: 'two.jpg',
            thumbnailUrl: '/two-320.jpg',
            width: 640,
            height: 360,
        },
    ];

    it('is a labelled radio group named by visible file names', async () => {
        const onSelect = vi.fn();
        const container = await render(
            <MediaGrid
                label="Available images"
                name="picker"
                items={items}
                selectedId={2}
                onSelect={onSelect}
            />,
        );

        expect(container.querySelector('legend')?.textContent).toBe(
            'Available images',
        );
        const radios = container.querySelectorAll<HTMLInputElement>(
            'input[type="radio"][name="picker"]',
        );
        expect(radios).toHaveLength(2);
        expect(radios[1]?.checked).toBe(true);
        expect(
            container.querySelector(`label[for="${radios[0]?.id}"]`)
                ?.textContent,
        ).toBe('one.jpg');
        expect(
            Array.from(container.querySelectorAll('img')).map((img) =>
                img.getAttribute('alt'),
            ),
        ).toEqual(['', '']);

        await act(async () => radios[0]!.click());
        expect(onSelect).toHaveBeenCalledWith(1);
    });
});

describe('MediaThumbnail', () => {
    it('falls back to a type icon without a preview', async () => {
        const container = await render(
            <MediaThumbnail src={null} alt="" kind="document" />,
        );

        expect(container.querySelector('img')).toBeNull();
        expect(
            container.querySelector('svg')?.getAttribute('aria-hidden'),
        ).toBe('true');
    });
});
