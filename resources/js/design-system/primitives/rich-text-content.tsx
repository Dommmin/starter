import { richTextTypography } from './rich-text-typography';

export type RichTextContentProps = {
    /**
     * Trusted HTML produced and sanitised on the server from the allowlisted
     * rich text schema (tiptap-php). Never pass user-supplied or client-built
     * HTML here.
     */
    html: string;
    className?: never;
    style?: never;
};

/**
 * Renders server-sanitised rich text with the DS content typography
 * (headings, lists, quotes, inline code, links). The only place in the
 * design system that injects HTML.
 */
export function RichTextContent({ html }: RichTextContentProps) {
    return (
        <div
            className={richTextTypography}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
