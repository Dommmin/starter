import { cn } from '@/lib/utils';

/**
 * Internal typography for rich text rendered from the closed Tiptap schema
 * (headings 2–4, paragraphs, lists, blockquote, inline code, links, rules,
 * DAM images).
 * Shared by `RichTextField` (editing surface) and `RichTextContent` (published
 * output) so both look the same. Not part of the public DS API.
 */
export const richTextTypography = cn(
    'text-foreground text-base leading-7 break-words',
    '[&>*+*]:mt-4',
    '[&_h2]:text-2xl [&_h2]:leading-tight [&_h2]:font-semibold [&_h2]:tracking-tight',
    '[&_h3]:text-xl [&_h3]:leading-snug [&_h3]:font-semibold',
    '[&_h4]:text-lg [&_h4]:leading-snug [&_h4]:font-semibold',
    '[&_ul]:list-disc [&_ul]:pl-6',
    '[&_ol]:list-decimal [&_ol]:pl-6',
    '[&_li]:mt-1 [&_li>p]:mt-0',
    '[&_blockquote]:border-border [&_blockquote]:text-muted-foreground [&_blockquote]:border-l-4 [&_blockquote]:pl-4 [&_blockquote]:italic',
    '[&_code]:bg-muted [&_code]:rounded-sm [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-sm',
    '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_a:hover]:no-underline',
    '[&_hr]:border-border [&_hr]:my-6',
    '[&_s]:line-through [&_strong]:font-semibold',
    '[&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-md [&_picture]:block',
    '[&_img.ProseMirror-selectednode]:ring-ring [&_img.ProseMirror-selectednode]:ring-2',
);

/** Internal frame (border, focus ring, invalid/disabled states) of the rich text editor. */
export function richTextFrame({
    invalid,
    disabled,
}: {
    invalid: boolean;
    disabled: boolean;
}): string {
    return cn(
        'border-input overflow-hidden rounded-md border bg-transparent shadow-xs transition-[color,box-shadow]',
        'focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px]',
        disabled && 'opacity-50',
        invalid && 'border-destructive focus-within:ring-destructive/20',
    );
}
