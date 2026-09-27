/**
 * Shared rich text contract (types and link allowlist) used by `RichTextField`,
 * its lazily loaded editor and `ResourceForm`. Free of Tiptap imports so it
 * does not pull the editor into the initial bundle.
 */

/** Inline mark stored in a rich text node (Tiptap/ProseMirror JSON). */
export type RichTextMark = {
    type: string;
    attrs?: Record<string, unknown>;
};

/** Node of a rich text document (Tiptap/ProseMirror JSON). */
export type RichTextNode = {
    type: string;
    attrs?: Record<string, unknown>;
    content?: RichTextNode[];
    marks?: RichTextMark[];
    text?: string;
};

/**
 * Rich text value exchanged with the backend: Tiptap JSON restricted to the
 * allowlisted schema (see `RichTextField`). The server stays the source of
 * validation and sanitisation.
 */
export type RichTextDocument = {
    type: 'doc';
    content?: RichTextNode[];
};

/** Translated toolbar and link-dialog labels, supplied by the screen (i18n). */
export type RichTextFieldLabels = {
    /** Accessible name of the formatting toolbar. */
    toolbar: string;
    heading2: string;
    heading3: string;
    heading4: string;
    bold: string;
    italic: string;
    strike: string;
    code: string;
    bulletList: string;
    orderedList: string;
    blockquote: string;
    horizontalRule: string;
    link: string;
    unlink: string;
    linkDialogTitle: string;
    linkUrlLabel: string;
    linkUrlHint: string;
    linkSubmit: string;
    linkCancel: string;
    linkClose: string;
    /** Shown when the URL is not an absolute http(s) or mailto address. */
    linkInvalid: string;
};

const ALLOWED_LINK_PROTOCOLS = new Set(['http:', 'https:', 'mailto:']);

/** Only absolute http(s) and mailto links are accepted (mirrors the backend allowlist). */
export function isAllowedRichTextHref(href: string): boolean {
    try {
        return ALLOWED_LINK_PROTOCOLS.has(new URL(href.trim()).protocol);
    } catch {
        return false;
    }
}

/** Clean DAM image offered by the rich text image picker. */
export type RichTextImageOption = {
    id: number;
    name: string;
    /** Default alternative text stored on the asset. */
    alt: string | null;
    thumbnailUrl: string;
    width: number;
    height: number;
};

/** Translated labels of the image toolbar button and picker dialog. */
export type RichTextImageLabels = {
    /** Toolbar button. */
    image: string;
    dialogTitle: string;
    dialogDescription: string;
    searchLabel: string;
    searchPlaceholder: string;
    searchClear: string;
    listLabel: string;
    loading: string;
    empty: string;
    error: string;
    retry: string;
    selectRequired: string;
    altLabel: string;
    altHint: string;
    submit: string;
    cancel: string;
    close: string;
};

/**
 * Enables inserting images from the DAM. Images are stored as
 * `{ type: 'image', attrs: { mediaId, alt } }`; URLs are never stored.
 */
export type RichTextImagePicker = {
    /** Loads clean images matching the search; rejects on failure. */
    load: (search: string) => Promise<RichTextImageOption[]>;
    /** Editor-only preview URL of an inserted image. */
    previewUrl: (mediaId: number) => string;
    labels: RichTextImageLabels;
};
