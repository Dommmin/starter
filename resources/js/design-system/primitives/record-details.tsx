import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from './card';
import { DescriptionList, type DescriptionListItem } from './description-list';

export type RecordDetailsProps = {
    title: string;
    description?: string;
    items: DescriptionListItem[];
    /** Rendered next to the title, typically an ActionMenu or Button. */
    actions?: ReactNode;
    emptyValuePlaceholder?: string;
};

export function RecordDetails({
    title,
    description,
    items,
    actions,
    emptyValuePlaceholder,
}: RecordDetailsProps) {
    return (
        <Card>
            <CardHeader>
                {/* Actions wrap under the title once it would get < 12rem. */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                        <CardTitle>{title}</CardTitle>
                        {description && (
                            <p className="text-muted-foreground text-sm">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && (
                        <div className="flex shrink-0 items-center gap-2">
                            {actions}
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <DescriptionList
                    items={items}
                    emptyValuePlaceholder={emptyValuePlaceholder}
                />
            </CardContent>
        </Card>
    );
}
