import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type SearchInputProps = {
    name: string;
    /** Translated accessible label, e.g. "Search users". */
    label: string;
    value: string;
    /**
     * Called with the committed value after `debounceMs`, or immediately when
     * the clear control is used. The caller owns URL/query state and issues
     * the Inertia request; this component only debounces local typing.
     */
    onChange: (value: string) => void;
    placeholder?: string;
    /** Translated label for the clear control. */
    clearLabel: string;
    debounceMs?: number;
    disabled?: boolean;
    className?: never;
    style?: never;
};

export function SearchInput({
    name,
    label,
    value,
    onChange,
    placeholder,
    clearLabel,
    debounceMs = 300,
    disabled = false,
}: SearchInputProps) {
    const [draft, setDraft] = useState(value);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    useEffect(() => {
        setDraft(value);
    }, [value]);

    useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    function commit(nextValue: string) {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
        setDraft(nextValue);
        onChange(nextValue);
    }

    function handleInput(nextValue: string) {
        setDraft(nextValue);
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
        timeoutRef.current = setTimeout(() => {
            onChange(nextValue);
        }, debounceMs);
    }

    return (
        <div className="relative w-full max-w-sm">
            <Search
                className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                aria-hidden="true"
            />
            <input
                type="search"
                name={name}
                aria-label={label}
                value={draft}
                onChange={(event) => handleInput(event.target.value)}
                placeholder={placeholder}
                disabled={disabled}
                className={cn(
                    'border-input placeholder:text-muted-foreground h-11 w-full rounded-md border bg-transparent py-2 pr-11 pl-9 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'disabled:pointer-events-none disabled:opacity-50',
                )}
            />
            {draft.length > 0 && (
                <button
                    type="button"
                    onClick={() => commit('')}
                    disabled={disabled}
                    aria-label={clearLabel}
                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1.5 flex size-8 -translate-y-1/2 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:outline-none"
                >
                    <X className="size-3.5" aria-hidden="true" />
                </button>
            )}
        </div>
    );
}
