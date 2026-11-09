import { ChevronDown, Loader2, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from './button';
import {
    lazyPopupTriggerProps,
    useLazyMenuState,
    useLazyModule,
    type LazyPopupTriggerProps,
} from './lazy-popup';

/** Outcome of one suggestion request: the proposed value or a translated error. */
export type FieldAssistResult = { suggestion: string } | { error: string };

/** One option of a helper with modes, e.g. "Shorten" of "Improve text". */
export type FieldAssistMode = { value: string; label: string };

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
    /**
     * With modes the trigger opens a menu and `request` receives the value of
     * the picked mode; without them the trigger requests at once.
     */
    modes?: readonly FieldAssistMode[];
    /** Fetches a suggestion; must resolve (errors as `{ error }`), not reject. */
    request: (mode?: string) => Promise<FieldAssistResult>;
};

/** One helper or several (e.g. "Suggest" and "Improve text") of one field. */
export type FieldAssistProp = FieldAssist | readonly FieldAssist[];

type FieldAssistState =
    | { status: 'idle' }
    | { status: 'pending'; index: number }
    | { status: 'preview'; index: number; suggestion: string }
    | { status: 'error'; message: string };

const loadFieldAssistMenu = () => import('./field-assist-menu');

/**
 * Trigger of a helper with modes. Same look as the ghost `Button` of a
 * helper without modes; the Radix menu loads on the first opening.
 */
function FieldAssistModeTrigger({
    assist,
    disabled,
    isPending,
    onSelect,
}: {
    assist: FieldAssist & { modes: readonly FieldAssistMode[] };
    disabled: boolean;
    isPending: boolean;
    onSelect: (mode: string) => void;
}) {
    const menu = useLazyMenuState();
    const { module, preload } = useLazyModule(loadFieldAssistMenu, menu.open);

    const renderTrigger = (props?: LazyPopupTriggerProps) => (
        <button
            type="button"
            disabled={disabled || isPending}
            aria-busy={isPending}
            className="focus-visible:ring-ring text-foreground hover:bg-surface-subtle inline-flex min-h-[44px] min-w-[44px] items-center justify-center gap-1.5 rounded-(--control-radius) px-3.5 py-1.5 text-xs font-medium transition-all duration-150 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none active:scale-[0.99] disabled:pointer-events-none disabled:opacity-50"
            {...props}
        >
            {isPending ? (
                <Loader2
                    className="size-4 shrink-0 animate-spin"
                    aria-hidden="true"
                />
            ) : (
                <Sparkles className="size-4 shrink-0" aria-hidden="true" />
            )}
            {assist.labels.request}
            <ChevronDown className="size-4 shrink-0" aria-hidden="true" />
        </button>
    );

    if (!module) {
        return renderTrigger(
            lazyPopupTriggerProps('menu', {
                open: menu.openFromTrigger,
                preload,
            }),
        );
    }

    const FieldAssistMenu = module.default;

    return (
        <FieldAssistMenu
            trigger={renderTrigger()}
            modes={assist.modes}
            open={menu.open}
            openedWithKeyboard={menu.openedWithKeyboard}
            onOpenChange={menu.setOpen}
            onSelect={onSelect}
        />
    );
}

function hasModes(
    assist: FieldAssist,
): assist is FieldAssist & { modes: readonly FieldAssistMode[] } {
    return assist.modes !== undefined && assist.modes.length > 0;
}

/**
 * Internal (not in the barrel): state machine of `FieldAssist`, shared by
 * `TextField` and `TextareaField`. Returns the triggers (rendered next to the
 * label) and the panel (rendered under the control). Several helpers share
 * one preview: a new request replaces the previous one.
 */
export function useFieldAssist({
    assist,
    disabled,
    onApply,
    focusControl,
    panelId,
}: {
    assist: FieldAssistProp | undefined;
    disabled: boolean;
    onApply: (value: string) => void;
    focusControl: () => void;
    panelId: string;
}) {
    const [state, setState] = useState<FieldAssistState>({ status: 'idle' });
    const assists: readonly FieldAssist[] =
        assist === undefined ? [] : 'labels' in assist ? [assist] : assist;

    if (assists.length === 0) {
        return { trigger: null, panel: null };
    }

    async function request(index: number, mode?: string) {
        setState({ status: 'pending', index });
        const result = await assists[index].request(mode);

        setState(
            'suggestion' in result
                ? { status: 'preview', index, suggestion: result.suggestion }
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

    const isPending = state.status === 'pending';
    const triggers = assists.map((helper, index) => {
        const isThisPending = isPending && state.index === index;

        return hasModes(helper) ? (
            <FieldAssistModeTrigger
                key={index}
                assist={helper}
                disabled={disabled || isPending}
                isPending={isThisPending}
                onSelect={(mode) => void request(index, mode)}
            />
        ) : (
            <Button
                key={index}
                variant="ghost"
                size="sm"
                isPending={isThisPending}
                disabled={disabled || isPending}
                onClick={() => void request(index)}
            >
                <Sparkles aria-hidden="true" />
                {helper.labels.request}
            </Button>
        );
    });

    const trigger =
        triggers.length === 1 ? (
            triggers[0]
        ) : (
            <div className="flex flex-wrap items-center gap-1">{triggers}</div>
        );

    const labels =
        state.status === 'preview' ? assists[state.index]?.labels : undefined;

    const panel = (
        <div id={panelId} aria-live="polite">
            {state.status === 'preview' && labels && (
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
