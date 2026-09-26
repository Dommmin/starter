import { useState, type ReactNode } from 'react';
import { Collapsible } from './collapsible';

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

export function Accordion({
    items,
    type = 'single',
    defaultOpenIds = [],
}: AccordionProps) {
    const [openIds, setOpenIds] = useState<string[]>(defaultOpenIds);

    function handleOpenChange(id: string, open: boolean) {
        setOpenIds((current) => {
            if (type === 'single') {
                return open ? [id] : [];
            }

            return open
                ? [...current, id]
                : current.filter((openId) => openId !== id);
        });
    }

    return (
        <div className="flex flex-col gap-2">
            {items.map((item) => (
                <Collapsible
                    key={item.id}
                    trigger={item.trigger}
                    open={openIds.includes(item.id)}
                    onOpenChange={(open) => handleOpenChange(item.id, open)}
                    disabled={item.disabled}
                >
                    {item.content}
                </Collapsible>
            ))}
        </div>
    );
}
