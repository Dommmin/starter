import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from './button';

/** Outcome of one suggestion request: the proposed value or a translated error. */
export type FieldAssistResult = { suggestion: string } | { error: string };

/**
 * Optional "suggest" helper of a text field (e.g. an AI proposal). The
 * suggestion is only a preview: the value changes after the user picks
 * "use", never on its own.
 */
export type FieldAssist = {
    /** Translated texts of the helper. */
    labels: {
        /** Trigger, e.g. "Suggest". */
        request: string;
        /** Heading of the preview, e.g. "Suggestion". */
        preview: string;
        apply: string;
        discard: string;
    };
    /** Fetches a suggestion; must resolve (errors as `{ error }`), not reject. */
    request: () => Promise<FieldAssistResult>;
};

type FieldAssistState =
    | { status: 'idle' }
    | { status: 'pending' }
    | { status: 'preview'; suggestion: string }
    | { status: 'error'; message: string };

/**
 * Internal (not in the barrel): state machine of `FieldAssist`, shared by
 * `TextField` and `TextareaField`. Returns the trigger (rendered next to the
 * label) and the panel (rendered under the control).
 */
export function useFieldAssist({
    assist,
    disabled,
    onApply,
    focusControl,
    panelId,
}: {
    assist: FieldAssist | undefined;
    disabled: boolean;
    onApply: (value: string) => void;
    focusControl: () => void;
    panelId: string;
}) {
    const [state, setState] = useState<FieldAssistState>({ status: 'idle' });

    if (!assist) {
        return { trigger: null, panel: null };
    }

    const { labels } = assist;

    async function request() {
        if (!assist) {
            return;
        }

        setState({ status: 'pending' });
        const result = await assist.request();

        setState(
            'suggestion' in result
                ? { status: 'preview', suggestion: result.suggestion }
                : { status: 'error', message: result.error },
        );
    }

    function close(apply: boolean) {
        if (apply && state.status === 'preview') {
            onApply(state.suggestion);
        }

        setState({ status: 'idle' });
        focusControl();
    }

    const trigger = (
        <Button
            variant="ghost"
            size="sm"
            isPending={state.status === 'pending'}
            disabled={disabled || state.status === 'pending'}
            onClick={() => void request()}
        >
            <Sparkles aria-hidden="true" />
            {labels.request}
        </Button>
    );

    const panel = (
        <div id={panelId} aria-live="polite">
            {state.status === 'preview' && (
                <div className="border-border-subtle bg-surface-subtle flex flex-col gap-2 rounded-md border p-3">
                    <p className="text-muted-foreground text-xs font-medium">
                        {labels.preview}
                    </p>
                    <p className="text-sm break-words whitespace-pre-wrap">
                        {state.suggestion}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" onClick={() => close(true)}>
                            {labels.apply}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => close(false)}
                        >
                            {labels.discard}
                        </Button>
                    </div>
                </div>
            )}
            {state.status === 'error' && (
                <p role="alert" className="text-status-danger text-xs">
                    {state.message}
                </p>
            )}
        </div>
    );

    return { trigger, panel };
}
