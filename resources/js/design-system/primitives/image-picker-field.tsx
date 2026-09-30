import { ImageIcon } from 'lucide-react';
import { lazy, Suspense, useId, useState } from 'react';
import { cn } from '@/lib/utils';
import { Button } from './button';
import type { RichTextImagePicker } from './rich-text-document';

export type ImagePickerFieldLabels = {
    /** Opens the picker while no image is selected. */
    choose: string;
    /** Opens the picker to replace the selected image. */
    change: string;
    remove: string;
    /** Shown in the preview frame while no image is selected. */
    empty: string;
    /** Alternative text of the preview thumbnail. */
    preview: string;
};

export type ImagePickerFieldProps = {
    /**
     * Stable id of the field group, e.g. for `ErrorSummary` focus. The group
     * is focusable programmatically and announces the label and messages.
     */
    id: string;
    name: string;
    label: string;
    hint?: string;
    /** Server or client validation message; presence marks the field invalid. */
    error?: string;
    required?: boolean;
    disabled?: boolean;
    /** Selected DAM asset id as a string; `''` means no image. */
    value: string;
    onChange: (value: string) => void;
    /** DAM access shared with the rich text image button. */
    picker: RichTextImagePicker;
    labels: ImagePickerFieldLabels;
    className?: never;
    style?: never;
};

const RichTextImageDialog = lazy(() =>
    import('./rich-text-image-dialog').then((module) => ({
        default: module.RichTextImageDialog,
    })),
);

/**
 * Single image chosen from the DAM (e.g. an article cover): a preview frame
 * with choose/change and remove actions. The picker dialog is loaded only
 * when first opened. Alternative text belongs to the asset itself.
 */
export function ImagePickerField({
    id,
    name,
    label,
    hint,
    error,
    required = false,
    disabled = false,
    value,
    onChange,
    picker,
    labels,
}: ImagePickerFieldProps) {
    const reactId = useId();
    const labelId = `${reactId}-label`;
    const hintId = hint ? `${reactId}-hint` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [hintId, errorId].filter(Boolean).join(' ') || undefined;
    const [isOpen, setIsOpen] = useState(false);
    const [hasOpened, setHasOpened] = useState(false);
    const mediaId = value === '' ? null : Number(value);

    function open() {
        setHasOpened(true);
        setIsOpen(true);
    }

    return (
        <div
            id={id}
            role="group"
            tabIndex={-1}
            aria-labelledby={labelId}
            aria-describedby={describedBy}
            className="focus-visible:ring-ring/50 @container flex flex-col gap-1.5 rounded-md outline-none focus-visible:ring-[3px]"
        >
            <span id={labelId} className="text-sm leading-snug font-medium">
                {label}
                {required && (
                    <span
                        className="text-status-danger ml-0.5"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </span>
            <div className="flex flex-col gap-3 @sm:flex-row @sm:items-center">
                <div
                    className={cn(
                        'bg-surface-subtle text-muted-foreground flex aspect-square w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border',
                        error ? 'border-destructive' : 'border-input',
                    )}
                >
                    {mediaId === null ? (
                        <span className="flex flex-col items-center gap-1 p-2 text-center text-xs">
                            <ImageIcon className="size-5" aria-hidden="true" />
                            {labels.empty}
                        </span>
                    ) : (
                        <img
                            src={picker.previewUrl(mediaId)}
                            alt={labels.preview}
                            className="size-full object-cover"
                        />
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        onClick={open}
                        disabled={disabled}
                    >
                        {mediaId === null ? labels.choose : labels.change}
                    </Button>
                    {mediaId !== null && (
                        <Button
                            variant="ghost"
                            onClick={() => onChange('')}
                            disabled={disabled}
                        >
                            {labels.remove}
                        </Button>
                    )}
                </div>
            </div>
            <input type="hidden" name={name} value={value} />
            {hint && (
                <p id={hintId} className="text-muted-foreground text-xs">
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-status-danger text-xs"
                >
                    {error}
                </p>
            )}
            {hasOpened && (
                <Suspense fallback={null}>
                    <RichTextImageDialog
                        open={isOpen}
                        onOpenChange={setIsOpen}
                        name={name}
                        picker={picker}
                        withAlt={false}
                        onInsert={(image) => onChange(String(image.mediaId))}
                    />
                </Suspense>
            )}
        </div>
    );
}
