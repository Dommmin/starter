import { FileText, ImageIcon } from 'lucide-react';
import { useId } from 'react';
import { cn } from '@/lib/utils';
import { Icon } from './icon';
import { Image } from './image';

type MediaThumbnailSize = 'sm' | 'md';

export type MediaThumbnailProps = {
    /** Public thumbnail URL; omit (null) for files without a preview. */
    src: string | null;
    /** Required alternative text; empty string when the name is shown next to it. */
    alt: string;
    width?: number;
    height?: number;
    /** Icon shown when there is no preview. */
    kind?: 'image' | 'document';
    size?: MediaThumbnailSize;
    className?: never;
    style?: never;
};

const thumbnailSizeMap: Record<MediaThumbnailSize, string> = {
    sm: 'size-12',
    md: 'size-20',
};

/**
 * Fixed-size square preview of a DAM file (cropped with `object-cover`),
 * or a type icon when no public variant exists.
 */
export function MediaThumbnail({
    src,
    alt,
    width = 320,
    height = 320,
    kind = 'image',
    size = 'sm',
}: MediaThumbnailProps) {
    return (
        <span
            className={cn(
                'bg-surface-subtle border-border-subtle flex shrink-0 items-center justify-center overflow-hidden rounded-md border',
                thumbnailSizeMap[size],
            )}
        >
            {src ? (
                <Image
                    src={src}
                    width={width}
                    height={height}
                    alt={alt}
                    fit="cover"
                />
            ) : (
                <Icon
                    icon={kind === 'document' ? FileText : ImageIcon}
                    tone="muted"
                    size="lg"
                />
            )}
        </span>
    );
}

export type MediaGridItem = {
    id: number;
    /** Visible caption and accessible name of the option. */
    name: string;
    thumbnailUrl: string;
    width: number;
    height: number;
};

export type MediaGridProps = {
    /** Translated accessible name of the option group. */
    label: string;
    /** Form field name of the radio group. */
    name: string;
    items: MediaGridItem[];
    selectedId: number | null;
    onSelect: (id: number) => void;
    className?: never;
    style?: never;
};

/**
 * Single-choice grid of media tiles built on native radio inputs: arrow keys
 * move the selection, Tab leaves the group, and each tile is labelled by its
 * visible file name. Responsive: 2 columns on phones up to 4 on desktop.
 */
export function MediaGrid({
    label,
    name,
    items,
    selectedId,
    onSelect,
}: MediaGridProps) {
    const groupId = useId();

    return (
        <fieldset className="min-w-0">
            <legend className="sr-only">{label}</legend>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                {items.map((item) => {
                    const inputId = `${groupId}-${item.id}`;
                    const isSelected = item.id === selectedId;

                    return (
                        <label
                            key={item.id}
                            htmlFor={inputId}
                            className={cn(
                                'group flex cursor-pointer flex-col gap-2 rounded-lg border p-2 transition-colors',
                                'has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-offset-2',
                                isSelected
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border-subtle hover:bg-surface-subtle',
                            )}
                        >
                            <input
                                id={inputId}
                                type="radio"
                                name={name}
                                value={item.id}
                                checked={isSelected}
                                onChange={() => onSelect(item.id)}
                                className="sr-only"
                            />
                            <span className="bg-surface-subtle aspect-square overflow-hidden rounded-md">
                                <Image
                                    src={item.thumbnailUrl}
                                    width={item.width}
                                    height={item.height}
                                    alt=""
                                    fit="cover"
                                />
                            </span>
                            <span className="text-foreground truncate text-xs font-medium">
                                {item.name}
                            </span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
