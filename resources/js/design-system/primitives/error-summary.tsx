import { AlertTriangle } from 'lucide-react';
import { useEffect, useRef } from 'react';

export type ErrorSummaryItem = {
    /** DOM id of the invalid control; used to move focus when activated. */
    fieldId: string;
    /** Translated field label. */
    label: string;
    message: string;
};

export type ErrorSummaryProps = {
    /** Translated heading, e.g. "There are 2 problems with your submission". */
    title: string;
    items: ErrorSummaryItem[];
    /**
     * Move focus to the first invalid field the moment `items` transitions
     * from empty to non-empty (e.g. right after a 422 response), per the
     * project's "focus na pierwszym błędzie" rule. Re-renders that keep the
     * same non-empty `items` (e.g. the user typing) do not steal focus again.
     */
    autoFocus?: boolean;
};

export function ErrorSummary({
    title,
    items,
    autoFocus = true,
}: ErrorSummaryProps) {
    const hasFocusedRef = useRef(false);

    useEffect(() => {
        if (items.length === 0) {
            hasFocusedRef.current = false;
            return;
        }

        if (autoFocus && !hasFocusedRef.current) {
            hasFocusedRef.current = true;
            document.getElementById(items[0].fieldId)?.focus();
        }
    }, [autoFocus, items]);

    if (items.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            className="border-destructive/30 bg-destructive/10 flex flex-col gap-2 rounded-lg border p-4"
        >
            <div className="flex items-center gap-2">
                <AlertTriangle
                    className="text-destructive size-4 shrink-0"
                    aria-hidden="true"
                />
                <p className="text-foreground font-medium">{title}</p>
            </div>
            <ul className="flex flex-col gap-1 pl-6 text-sm">
                {items.map((item) => (
                    <li
                        key={item.fieldId}
                        className="text-foreground list-disc"
                    >
                        <a
                            href={`#${item.fieldId}`}
                            className="text-foreground underline underline-offset-4 hover:no-underline"
                            onClick={(event) => {
                                event.preventDefault();
                                document.getElementById(item.fieldId)?.focus();
                            }}
                        >
                            {item.label}: {item.message}
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}
