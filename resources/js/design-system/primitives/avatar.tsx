import * as AvatarPrimitive from '@radix-ui/react-avatar';
import { cn } from '@/lib/utils';

type AvatarSize = 'sm' | 'default' | 'lg';

export type AvatarProps = {
    /** Accessible name for the person or entity represented. */
    name: string;
    src?: string;
    /** Fallback shown while the image loads or when it fails; usually initials. */
    fallback: string;
    size?: AvatarSize;
};

const sizeMap: Record<AvatarSize, string> = {
    sm: 'size-8 text-xs',
    default: 'size-10 text-sm',
    lg: 'size-12 text-base',
};

export function Avatar({ name, src, fallback, size = 'default' }: AvatarProps) {
    return (
        <AvatarPrimitive.Root
            className={cn(
                'relative flex shrink-0 overflow-hidden rounded-full',
                sizeMap[size],
            )}
        >
            {src && (
                <AvatarPrimitive.Image
                    src={src}
                    alt={name}
                    className="aspect-square size-full object-cover"
                />
            )}
            <AvatarPrimitive.Fallback
                delayMs={src ? 400 : 0}
                className="bg-muted text-muted-foreground flex size-full items-center justify-center rounded-full font-medium"
            >
                {fallback}
            </AvatarPrimitive.Fallback>
        </AvatarPrimitive.Root>
    );
}
