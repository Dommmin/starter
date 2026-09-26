import { cn } from '@/lib/utils';

type SkeletonShape = 'text' | 'circle' | 'rect';
type SkeletonSize = 'sm' | 'default' | 'lg';

export type SkeletonProps = {
    shape?: SkeletonShape;
    size?: SkeletonSize;
    /** Renders multiple stacked text lines; only meaningful for shape="text". */
    lines?: number;
};

const heightMap: Record<SkeletonSize, string> = {
    sm: 'h-3',
    default: 'h-4',
    lg: 'h-6',
};

const circleSizeMap: Record<SkeletonSize, string> = {
    sm: 'size-8',
    default: 'size-10',
    lg: 'size-14',
};

const rectHeightMap: Record<SkeletonSize, string> = {
    sm: 'h-16',
    default: 'h-24',
    lg: 'h-40',
};

export function Skeleton({
    shape = 'text',
    size = 'default',
    lines = 1,
}: SkeletonProps) {
    if (shape === 'circle') {
        return (
            <span
                aria-hidden="true"
                className={cn(
                    'bg-muted block animate-pulse rounded-full',
                    circleSizeMap[size],
                )}
            />
        );
    }

    if (shape === 'rect') {
        return (
            <span
                aria-hidden="true"
                className={cn(
                    'bg-muted block w-full animate-pulse rounded-lg',
                    rectHeightMap[size],
                )}
            />
        );
    }

    return (
        <span aria-hidden="true" className="flex flex-col gap-2">
            {Array.from({ length: lines }, (_, index) => (
                <span
                    key={index}
                    className={cn(
                        'bg-muted block animate-pulse rounded-md',
                        heightMap[size],
                        index === lines - 1 && lines > 1 ? 'w-2/3' : 'w-full',
                    )}
                />
            ))}
        </span>
    );
}
