import { ChevronDown } from 'lucide-react';
import { useState, type MouseEvent, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type AccordionItem = {
    id: string;
    trigger: ReactNode;
    content: ReactNode;
    disabled?: boolean;
};

type AccordionType = 'single' | 'multiple';

export type AccordionProps = {
    items: AccordionItem[];
    type?: AccordionType;
    defaultOpenIds?: string[];
    className?: never;
    style?: never;
};

/**
 * Disclosure list on native `<details>`/`<summary>`: the summary is the
 * focusable toggle (Enter/Space, native expanded state, no extra roles) and
 * the content is always in the server-rendered HTML, only hidden while
 * closed. `single` keeps at most one item open. A disabled item cannot be
 * toggled (`aria-disabled` on the summary).
 */
export function Accordion({
    items,
    type = 'single',
    defaultOpenIds = [],
}: AccordionProps) {
    const [openIds, setOpenIds] = useState<string[]>(defaultOpenIds);

    function handleToggle(id: string, open: boolean) {
        setOpenIds((current) => {
            if (open === current.includes(id)) {
                return current;
            }

            if (type === 'single') {
                return open ? [id] : [];
            }

            return open
                ? [...current, id]
                : current.filter((openId) => openId !== id);
        });
    }

    function preventWhenDisabled(
        event: MouseEvent<HTMLElement>,
        disabled: boolean | undefined,
    ) {
        if (disabled) {
            event.preventDefault();
        }
    }

    return (
        <div className="flex flex-col gap-2">
            {items.map((item) => (
                <details
                    key={item.id}
                    open={openIds.includes(item.id)}
                    onToggle={(event) => {
                        const element = event.currentTarget;

                        // Safety net where the prevented summary click still toggles.
                        if (item.disabled && element.open) {
                            element.open = false;

                            return;
                        }

                        handleToggle(item.id, element.open);
                    }}
                    className="group border-border-subtle rounded-lg border"
                >
                    <summary
                        aria-disabled={item.disabled ? true : undefined}
                        onClick={(event) =>
                            preventWhenDisabled(event, item.disabled)
                        }
                        className={cn(
                            'flex min-h-11 w-full cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium [&::-webkit-details-marker]:hidden',
                            'focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none',
                            item.disabled && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        {item.trigger}
                        <ChevronDown
                            className="text-muted-foreground size-4 shrink-0 group-open:rotate-180 motion-safe:transition-transform"
                            aria-hidden="true"
                        />
                    </summary>
                    <div className="text-muted-foreground px-4 pb-4 text-sm">
                        {item.content}
                    </div>
                </details>
            ))}
        </div>
    );
}
