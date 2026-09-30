import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);

vi.mock('sonner', () => ({ toast }));

const { notify } = await import('./notify');

beforeEach(() => {
    toast.mockClear();
    toast.success.mockClear();
    toast.error.mockClear();
});

describe('notify', () => {
    it('shows a success toast with an optional description', () => {
        notify({ tone: 'success', message: 'Saved', description: 'Live now' });

        expect(toast.success).toHaveBeenCalledWith('Saved', {
            description: 'Live now',
        });
    });

    it('maps the danger tone to an error toast', () => {
        notify({ tone: 'danger', message: 'Failed' });

        expect(toast.error).toHaveBeenCalledWith('Failed', undefined);
        expect(toast.success).not.toHaveBeenCalled();
    });

    it('shows a neutral toast without a status tone', () => {
        notify({ tone: 'neutral', message: 'Queued' });

        expect(toast).toHaveBeenCalledWith('Queued', undefined);
        expect(toast.error).not.toHaveBeenCalled();
        expect(toast.success).not.toHaveBeenCalled();
    });
});

/** Files allowed to import `sonner`: this primitive and the vendored Toaster. */
const SONNER_ALLOWED = new Set([
    'resources/js/design-system/primitives/notify.ts',
    'resources/js/components/ui/sonner.tsx',
]);

/** Wayfinder output: generated, never imports UI libraries. */
const GENERATED_DIRECTORIES = new Set(['actions', 'routes', 'wayfinder']);

function sourceFiles(directory: string, isRoot = true): string[] {
    return readdirSync(directory).flatMap((entry) => {
        const path = join(directory, entry);

        if (statSync(path).isDirectory()) {
            return isRoot && GENERATED_DIRECTORIES.has(entry)
                ? []
                : sourceFiles(path, false);
        }

        return /\.(ts|tsx)$/.test(entry) && !/\.test\.tsx?$/.test(entry)
            ? [path]
            : [];
    });
}

describe('sonner boundary', () => {
    it('is imported only by notify and the vendored Toaster', () => {
        const root = resolve(import.meta.dirname, '../../../..');
        const offenders = sourceFiles(resolve(root, 'resources/js'))
            .map((file) => relative(root, file))
            .filter((file) => !SONNER_ALLOWED.has(file))
            .filter((file) =>
                /from\s+['"]sonner['"]|import\(\s*['"]sonner['"]\s*\)/.test(
                    readFileSync(resolve(root, file), 'utf8'),
                ),
            );

        expect(offenders).toEqual([]);
    });
});
