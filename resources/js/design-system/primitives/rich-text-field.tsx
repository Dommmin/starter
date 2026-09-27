import { lazy, Suspense, useEffect, useId, useState } from 'react';
import type {
    RichTextDocument,
    RichTextFieldLabels,
    RichTextImagePicker,
} from './rich-text-document';
import { richTextFrame } from './rich-text-typography';
import { Skeleton } from './skeleton';

export {
    isAllowedRichTextHref,
    type RichTextDocument,
    type RichTextFieldLabels,
    type RichTextImageLabels,
    type RichTextImageOption,
    type RichTextImagePicker,
    type RichTextMark,
    type RichTextNode,
} from './rich-text-document';

export type RichTextFieldProps = {
    /** Stable id of the editable surface, e.g. for `ErrorSummary` focus. */
    id: string;
    name: string;
    label: string;
    hint?: string;
    /** Server or client validation message; presence marks the field invalid. */
    error?: string;
    required?: boolean;
    disabled?: boolean;
    value: RichTextDocument;
    onChange: (value: RichTextDocument) => void;
    /** Translated toolbar and link-dialog labels (i18n from the screen). */
    labels: RichTextFieldLabels;
    /**
     * Enables the image button (DAM picker). Without it existing image nodes
     * are kept but no new image can be inserted.
     */
    imagePicker?: RichTextImagePicker;
    className?: never;
    style?: never;
};

const RichTextEditor = lazy(() =>
    import('./rich-text-editor').then((module) => ({
        default: module.RichTextEditor,
    })),
);

/**
 * Controlled rich text field backed by Tiptap v3 with a closed schema
 * (paragraph, headings 2–4, bold, italic, strike, code, lists, blockquote,
 * hard break, horizontal rule, http(s)/mailto links, block images from the
 * DAM picker).
 *
 * SSR and the first client render show a skeleton; the editor chunk is
 * loaded only after mount, so Tiptap never enters the SSR output or the
 * initial bundle of screens that merely import the design system.
 */
export function RichTextField({
    id,
    name,
    label,
    hint,
    error,
    required = false,
    disabled = false,
    value,
    onChange,
    labels,
    imagePicker,
}: RichTextFieldProps) {
    const reactId = useId();
    const labelId = `${reactId}-label`;
    const hintId = hint ? `${reactId}-hint` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [hintId, errorId].filter(Boolean).join(' ') || undefined;
    const [isMounted, setIsMounted] = useState(false);

    useEffect(() => {
        setIsMounted(true);
    }, []);

    const fallback = (
        <div className={richTextFrame({ invalid: Boolean(error), disabled })}>
            <div className="bg-surface-subtle border-input h-13 border-b" />
            <div className="min-h-40 px-3 py-2">
                <Skeleton shape="text" lines={3} />
            </div>
        </div>
    );

    return (
        <div className="flex flex-col gap-1.5">
            <span id={labelId} className="text-sm leading-none font-medium">
                {label}
                {required && (
                    <span
                        className="text-destructive ml-0.5"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </span>
            {isMounted ? (
                <Suspense fallback={fallback}>
                    <RichTextEditor
                        id={id}
                        name={name}
                        labelId={labelId}
                        describedBy={describedBy}
                        invalid={Boolean(error)}
                        required={required}
                        disabled={disabled}
                        value={value}
                        onChange={onChange}
                        labels={labels}
                        imagePicker={imagePicker}
                    />
                </Suspense>
            ) : (
                fallback
            )}
            <input type="hidden" name={name} value={JSON.stringify(value)} />
            {hint && (
                <p id={hintId} className="text-muted-foreground text-xs">
                    {hint}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-destructive text-xs"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
