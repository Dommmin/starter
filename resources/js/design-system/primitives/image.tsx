/** One modern-format `<source>` of a responsive picture (e.g. AVIF, WebP). */
export type ImageSource = {
    /** MIME type, e.g. `image/avif`. */
    type: string;
    /** Width-descriptor srcset, e.g. `/a-320.avif 320w, /a-640.avif 640w`. */
    srcset: string;
};

type ImageFit = 'intrinsic' | 'cover';

export type ImageProps = {
    /** Fallback URL (largest fallback variant). */
    src: string;
    /** Width-descriptor srcset of the fallback format. */
    srcset?: string;
    /** Modern formats in preference order; rendered as `<picture>` sources. */
    sources?: ImageSource[];
    /** Intrinsic size: required so the browser reserves space (no layout shift). */
    width: number;
    height: number;
    /**
     * Required alternative text. Pass an empty string only for decorative
     * images (announced as nothing by screen readers).
     */
    alt: string;
    /** Rendered slot width, e.g. `(min-width: 48rem) 48rem, 100vw`. */
    sizes?: string;
    /**
     * The LCP image: loads eagerly with `fetchpriority="high"`. Everything
     * else is lazy with async decoding.
     */
    priority?: boolean;
    /** `intrinsic` scales down within its container; `cover` fills a sized frame. */
    fit?: ImageFit;
    className?: never;
    style?: never;
};

const fitMap: Record<ImageFit, string> = {
    intrinsic: 'block h-auto max-w-full',
    cover: 'block size-full object-cover',
};

/**
 * Responsive image with mandatory dimensions and alternative text. Renders a
 * `<picture>` when modern sources are given, with the fallback `<img>` last.
 */
export function Image({
    src,
    srcset,
    sources = [],
    width,
    height,
    alt,
    sizes,
    priority = false,
    fit = 'intrinsic',
}: ImageProps) {
    const image = (
        <img
            src={src}
            srcSet={srcset}
            sizes={srcset ? sizes : undefined}
            width={width}
            height={height}
            alt={alt}
            loading={priority ? 'eager' : 'lazy'}
            decoding={priority ? undefined : 'async'}
            fetchPriority={priority ? 'high' : undefined}
            className={fitMap[fit]}
        />
    );

    if (sources.length === 0) {
        return image;
    }

    return (
        <picture className={fit === 'cover' ? 'block size-full' : 'contents'}>
            {sources.map((source) => (
                <source
                    key={source.type}
                    type={source.type}
                    srcSet={source.srcset}
                    sizes={sizes}
                />
            ))}
            {image}
        </picture>
    );
}
