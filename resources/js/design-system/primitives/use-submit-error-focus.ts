import { useEffect, useRef } from 'react';

/**
 * Internal (not in the barrel): moves focus to the first invalid field after
 * *every* failed submit, not only when the error count goes from 0 to N
 * (that case is handled by `ErrorSummary` itself). Call the returned
 * function right before issuing the request; the next settled render
 * (`isPending` false, new `errors` object) focuses `fieldIds[0]`.
 */
export function useSubmitErrorFocus(
    fieldIds: string[],
    errors: unknown,
    isPending: boolean,
): () => void {
    const isAwaitingResultRef = useRef(false);
    const firstFieldIdRef = useRef<string | undefined>(undefined);
    firstFieldIdRef.current = fieldIds[0];

    useEffect(() => {
        if (!isAwaitingResultRef.current || isPending) {
            return;
        }

        isAwaitingResultRef.current = false;

        if (firstFieldIdRef.current) {
            document.getElementById(firstFieldIdRef.current)?.focus();
        }
    }, [errors, isPending]);

    return () => {
        isAwaitingResultRef.current = true;
    };
}
