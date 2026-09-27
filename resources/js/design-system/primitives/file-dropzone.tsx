import { CheckCircle2, CircleAlert, Upload, X } from 'lucide-react';
import { useId, useRef, useState, type DragEvent } from 'react';
import { cn } from '@/lib/utils';
import { Button } from './button';
import { Icon } from './icon';
import { IconButton } from './icon-button';
import { Progress } from './progress';

export type FileDropzoneProps = {
    /** Translated visible title, also the accessible name of the drop region. */
    label: string;
    /** Translated constraints, e.g. allowed types and the size limit. */
    hint?: string;
    /** Translated label of the button that opens the file chooser. */
    chooseLabel: string;
    /** Native `accept` hint, e.g. `.jpg,.png,.pdf`; the server still decides. */
    accept?: string;
    multiple?: boolean;
    disabled?: boolean;
    /** Receives dropped or chosen files; the caller validates and uploads. */
    onFilesSelected: (files: File[]) => void;
    className?: never;
    style?: never;
};

/**
 * Drop target plus a keyboard-accessible "choose files" button. Dragging is
 * a pointer shortcut only: the button opens the native chooser, so the
 * control works with keyboard, touch and screen readers.
 */
export function FileDropzone({
    label,
    hint,
    chooseLabel,
    accept,
    multiple = false,
    disabled = false,
    onFilesSelected,
}: FileDropzoneProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const labelId = useId();
    const hintId = useId();

    function emit(list: FileList | null) {
        const files = Array.from(list ?? []);

        if (!disabled && files.length > 0) {
            onFilesSelected(multiple ? files : files.slice(0, 1));
        }
    }

    function handleDrag(event: DragEvent<HTMLDivElement>, dragging: boolean) {
        event.preventDefault();
        setIsDragging(!disabled && dragging);
    }

    return (
        <div
            role="group"
            aria-labelledby={labelId}
            aria-describedby={hint ? hintId : undefined}
            onDragEnter={(event) => handleDrag(event, true)}
            onDragOver={(event) => handleDrag(event, true)}
            onDragLeave={(event) => handleDrag(event, false)}
            onDrop={(event) => {
                handleDrag(event, false);
                emit(event.dataTransfer.files);
            }}
            className={cn(
                'flex flex-col items-center gap-3 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors sm:px-6',
                isDragging
                    ? 'border-primary bg-primary/5'
                    : 'border-border-subtle bg-surface-subtle',
                disabled && 'opacity-50',
            )}
        >
            <Icon icon={Upload} size="lg" tone="muted" />
            <p id={labelId} className="text-foreground text-sm font-medium">
                {label}
            </p>
            {hint && (
                <p
                    id={hintId}
                    className="text-muted-foreground max-w-prose text-xs"
                >
                    {hint}
                </p>
            )}
            <Button
                variant="outline"
                disabled={disabled}
                onClick={() => inputRef.current?.click()}
            >
                {chooseLabel}
            </Button>
            <input
                ref={inputRef}
                type="file"
                tabIndex={-1}
                aria-hidden="true"
                className="sr-only"
                accept={accept}
                multiple={multiple}
                disabled={disabled}
                onChange={(event) => {
                    emit(event.currentTarget.files);
                    event.currentTarget.value = '';
                }}
            />
        </div>
    );
}

export type UploadQueueStatus = 'queued' | 'uploading' | 'done' | 'error';

export type UploadQueueItem = {
    id: string;
    name: string;
    status: UploadQueueStatus;
    /** 0-100 while uploading. */
    progress?: number;
    /** Translated status line, e.g. "Uploading… 40%" or the error. */
    message: string;
};

export type UploadQueueProps = {
    /** Translated accessible name of the list. */
    label: string;
    items: UploadQueueItem[];
    /** Translated accessible name of the dismiss control per item. */
    dismissLabel: (item: UploadQueueItem) => string;
    /** Dismiss finished or failed items; omit to hide the control. */
    onDismiss?: (id: string) => void;
    className?: never;
    style?: never;
};

const statusTone: Record<UploadQueueStatus, string> = {
    queued: 'text-muted-foreground',
    uploading: 'text-muted-foreground',
    done: 'text-status-success',
    error: 'text-destructive',
};

/**
 * Per-file upload progress and result. Status lines live in a polite live
 * region; errors stay next to the file they belong to.
 */
export function UploadQueue({
    label,
    items,
    dismissLabel,
    onDismiss,
}: UploadQueueProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <ul
            aria-label={label}
            className="border-border-subtle divide-border-subtle flex flex-col divide-y rounded-lg border"
        >
            {items.map((item) => (
                <li
                    key={item.id}
                    className="flex items-start gap-3 px-3 py-2 sm:px-4"
                >
                    <span className="mt-0.5 shrink-0">
                        {item.status === 'done' && (
                            <Icon icon={CheckCircle2} tone="success" />
                        )}
                        {item.status === 'error' && (
                            <Icon icon={CircleAlert} tone="danger" />
                        )}
                        {(item.status === 'queued' ||
                            item.status === 'uploading') && (
                            <Icon icon={Upload} tone="muted" />
                        )}
                    </span>
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <span className="text-foreground truncate text-sm font-medium">
                            {item.name}
                        </span>
                        {item.status === 'uploading' && (
                            <Progress
                                label={item.message}
                                value={item.progress}
                            />
                        )}
                        <span
                            aria-live="polite"
                            className={cn('text-xs', statusTone[item.status])}
                        >
                            {item.message}
                        </span>
                    </div>
                    {onDismiss &&
                        (item.status === 'done' || item.status === 'error') && (
                            <IconButton
                                icon={X}
                                size="sm"
                                variant="ghost"
                                ariaLabel={dismissLabel(item)}
                                onClick={() => onDismiss(item.id)}
                            />
                        )}
                </li>
            ))}
        </ul>
    );
}
