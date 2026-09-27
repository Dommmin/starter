import { ImageIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { EmptyState } from './empty-state';
import { FormDialog } from './form-dialog';
import { MediaGrid } from './media-grid';
import { RetryPanel } from './retry-panel';
import type {
    RichTextImageOption,
    RichTextImagePicker,
} from './rich-text-document';
import { SearchInput } from './search-input';
import { Skeleton } from './skeleton';
import { Stack } from './stack';
import { TextField } from './text-field';

export type RichTextImageDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    name: string;
    picker: RichTextImagePicker;
    onInsert: (image: { mediaId: number; alt: string }) => void;
};

type LoadState = 'loading' | 'ready' | 'error';

/**
 * Internal DAM picker of the rich text editor (not public DS API): search,
 * single-choice grid of clean images, alternative text prefilled from the
 * asset. Loaded lazily together with the editor.
 */
export function RichTextImageDialog({
    open,
    onOpenChange,
    name,
    picker,
    onInsert,
}: RichTextImageDialogProps) {
    const { labels } = picker;
    const [search, setSearch] = useState('');
    const [items, setItems] = useState<RichTextImageOption[]>([]);
    const [state, setState] = useState<LoadState>('loading');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [alt, setAlt] = useState('');
    const [error, setError] = useState<string | undefined>();
    const requestRef = useRef(0);
    const loadRef = useRef(picker.load);

    useEffect(() => {
        loadRef.current = picker.load;
    });

    const load = useCallback((query: string) => {
        const request = ++requestRef.current;
        setState('loading');

        loadRef
            .current(query)
            .then((result) => {
                if (request === requestRef.current) {
                    setItems(result);
                    setState('ready');
                }
            })
            .catch(() => {
                if (request === requestRef.current) {
                    setState('error');
                }
            });
    }, []);

    useEffect(() => {
        if (open) {
            setSearch('');
            setSelectedId(null);
            setAlt('');
            setError(undefined);
            load('');
        }
    }, [open, load]);

    function select(id: number) {
        setSelectedId(id);
        setError(undefined);
        setAlt(items.find((item) => item.id === id)?.alt ?? '');
    }

    function submit() {
        if (selectedId === null) {
            setError(labels.selectRequired);
            return;
        }

        onInsert({ mediaId: selectedId, alt: alt.trim() });
        onOpenChange(false);
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={labels.dialogTitle}
            description={labels.dialogDescription}
            submitLabel={labels.submit}
            cancelLabel={labels.cancel}
            closeLabel={labels.close}
            onSubmit={submit}
            error={error}
        >
            <Stack gap="tight">
                <SearchInput
                    name={`${name}-image-search`}
                    label={labels.searchLabel}
                    placeholder={labels.searchPlaceholder}
                    clearLabel={labels.searchClear}
                    value={search}
                    onChange={(value) => {
                        setSearch(value);
                        load(value);
                    }}
                />
                <div
                    aria-busy={state === 'loading'}
                    className="max-h-[50vh] min-h-32 overflow-y-auto p-1"
                >
                    {state === 'loading' && (
                        <Stack gap="tight">
                            <span className="sr-only" role="status">
                                {labels.loading}
                            </span>
                            <Skeleton shape="text" lines={4} />
                        </Stack>
                    )}
                    {state === 'error' && (
                        <RetryPanel
                            title={labels.error}
                            retryLabel={labels.retry}
                            onRetry={() => load(search)}
                        />
                    )}
                    {state === 'ready' && items.length === 0 && (
                        <EmptyState title={labels.empty} icon={ImageIcon} />
                    )}
                    {state === 'ready' && items.length > 0 && (
                        <MediaGrid
                            label={labels.listLabel}
                            name={`${name}-image`}
                            items={items}
                            selectedId={selectedId}
                            onSelect={select}
                        />
                    )}
                </div>
                <TextField
                    name={`${name}-image-alt`}
                    label={labels.altLabel}
                    description={labels.altHint}
                    value={alt}
                    onChange={setAlt}
                    disabled={selectedId === null}
                />
            </Stack>
        </FormDialog>
    );
}
