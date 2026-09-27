import {
    EditorContent,
    mergeAttributes,
    Node,
    useEditor,
    useEditorState,
    type Editor,
} from '@tiptap/react';
import { StarterKit } from '@tiptap/starter-kit';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    Heading4,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Strikethrough,
    Unlink,
    type LucideIcon,
} from 'lucide-react';
import {
    lazy,
    Suspense,
    useEffect,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
} from 'react';
import { cn } from '@/lib/utils';
import { FormDialog } from './form-dialog';
import {
    isAllowedRichTextHref,
    type RichTextDocument,
    type RichTextFieldLabels,
    type RichTextImagePicker,
} from './rich-text-document';
import { richTextFrame, richTextTypography } from './rich-text-typography';
import { Skeleton } from './skeleton';
import { TextField } from './text-field';

const RichTextImageDialog = lazy(() =>
    import('./rich-text-image-dialog').then((module) => ({
        default: module.RichTextImageDialog,
    })),
);

type MediaImageOptions = {
    /** Resolves the editor-only preview URL of a media id. */
    previewUrl: (mediaId: number) => string;
};

/**
 * Block image referencing a DAM asset. Only `mediaId` and `alt` are stored;
 * the preview `src` exists in the editor DOM only. HTML is parsed back only
 * from `img[data-media-id]` (copy/paste inside the editor); the server
 * accepts ids of clean DAM images only.
 */
const MediaImage = Node.create<MediaImageOptions>({
    name: 'image',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,

    addOptions() {
        return { previewUrl: () => '' };
    },

    addAttributes() {
        return {
            mediaId: {
                default: null,
                parseHTML: (element) => {
                    const id = Number(element.getAttribute('data-media-id'));
                    return Number.isInteger(id) && id > 0 ? id : null;
                },
                renderHTML: (attributes) => ({
                    'data-media-id': String(attributes.mediaId),
                }),
            },
            alt: {
                default: '',
                parseHTML: (element) => element.getAttribute('alt') ?? '',
                renderHTML: (attributes) => ({
                    alt:
                        typeof attributes.alt === 'string'
                            ? attributes.alt
                            : '',
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'img[data-media-id]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        const mediaId = node.attrs.mediaId as number | null;

        return [
            'img',
            mergeAttributes(HTMLAttributes, {
                src: mediaId ? this.options.previewUrl(mediaId) : '',
                loading: 'lazy',
                decoding: 'async',
                draggable: 'false',
            }),
        ];
    },
});

/**
 * Closed schema: paragraph, heading (2–4), bold, italic, strike, code,
 * bulletList, orderedList, listItem, blockquote, hardBreak, horizontalRule,
 * link and block `image` (DAM reference). Code blocks and underline are
 * disabled; anything else pasted into the editor is dropped by the schema.
 */
function createExtensions(previewUrl: (mediaId: number) => string) {
    return [
        StarterKit.configure({
            codeBlock: false,
            underline: false,
            heading: { levels: [2, 3, 4] },
            link: {
                openOnClick: false,
                autolink: true,
                linkOnPaste: true,
                defaultProtocol: 'https',
                protocols: ['mailto'],
                isAllowedUri: (url) => isAllowedRichTextHref(url),
                HTMLAttributes: {
                    rel: 'noopener noreferrer nofollow',
                    target: null,
                },
            },
        }),
        MediaImage.configure({ previewUrl }),
    ];
}

const EMPTY_DOCUMENT: RichTextDocument = {
    type: 'doc',
    content: [{ type: 'paragraph' }],
};

function normalizeDocument(value: RichTextDocument): RichTextDocument {
    return value.content && value.content.length > 0 ? value : EMPTY_DOCUMENT;
}

type ToolKey =
    | 'heading2'
    | 'heading3'
    | 'heading4'
    | 'bold'
    | 'italic'
    | 'strike'
    | 'code'
    | 'bulletList'
    | 'orderedList'
    | 'blockquote'
    | 'horizontalRule'
    | 'link'
    | 'unlink'
    | 'image';

type ToolbarItem = {
    key: ToolKey;
    icon: LucideIcon;
    /** Present only for toggle buttons; rendered as `aria-pressed`. */
    pressed?: boolean;
    disabled?: boolean;
    shortcut?: string;
    opensDialog?: boolean;
    run: (editor: Editor) => void;
};

type ActiveState = Record<
    Exclude<ToolKey, 'horizontalRule' | 'unlink' | 'image'>,
    boolean
>;

function selectActiveState(editor: Editor | null): ActiveState | null {
    if (!editor) {
        return null;
    }

    return {
        heading2: editor.isActive('heading', { level: 2 }),
        heading3: editor.isActive('heading', { level: 3 }),
        heading4: editor.isActive('heading', { level: 4 }),
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        strike: editor.isActive('strike'),
        code: editor.isActive('code'),
        bulletList: editor.isActive('bulletList'),
        orderedList: editor.isActive('orderedList'),
        blockquote: editor.isActive('blockquote'),
        link: editor.isActive('link'),
    };
}

export type RichTextEditorProps = {
    id: string;
    name: string;
    labelId: string;
    describedBy?: string;
    invalid: boolean;
    required: boolean;
    disabled: boolean;
    value: RichTextDocument;
    onChange: (value: RichTextDocument) => void;
    labels: RichTextFieldLabels;
    imagePicker?: RichTextImagePicker;
};

/**
 * Tiptap v3 editor behind `RichTextField`: closed schema, labelled toolbar
 * (roving tab stop, `aria-pressed` toggles, Tiptap keyboard shortcuts) and a
 * link dialog accepting http(s)/mailto only. Loaded lazily on the client, so
 * Tiptap never lands in the initial or SSR-critical bundle of other screens.
 */
export function RichTextEditor({
    id,
    name,
    labelId,
    describedBy,
    invalid,
    required,
    disabled,
    value,
    onChange,
    labels,
    imagePicker,
}: RichTextEditorProps) {
    const onChangeRef = useRef(onChange);
    const lastEmittedRef = useRef<RichTextDocument | null>(null);
    const previewUrlRef = useRef(imagePicker?.previewUrl);

    useEffect(() => {
        onChangeRef.current = onChange;
        previewUrlRef.current = imagePicker?.previewUrl;
    });

    const extensions = useMemo(
        () =>
            createExtensions(
                (mediaId) => previewUrlRef.current?.(mediaId) ?? '',
            ),
        [],
    );

    const editorProps = useMemo(() => {
        const attributes: Record<string, string> = {
            id,
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-labelledby': labelId,
            class: cn('min-h-40 px-3 py-2 outline-none', richTextTypography),
        };

        if (describedBy) {
            attributes['aria-describedby'] = describedBy;
        }

        if (invalid) {
            attributes['aria-invalid'] = 'true';
        }

        if (required) {
            attributes['aria-required'] = 'true';
        }

        if (disabled) {
            attributes['aria-disabled'] = 'true';
        }

        return { attributes };
    }, [id, labelId, describedBy, invalid, required, disabled]);

    const editor = useEditor({
        extensions,
        content: normalizeDocument(value),
        editable: !disabled,
        immediatelyRender: false,
        editorProps,
        onUpdate: ({ editor: current }) => {
            const next = current.getJSON() as RichTextDocument;
            lastEmittedRef.current = next;
            onChangeRef.current(next);
        },
    });

    // Controlled value: apply external changes (e.g. form reset) without
    // echoing them back through `onChange`.
    useEffect(() => {
        if (!editor || editor.isDestroyed || value === lastEmittedRef.current) {
            return;
        }

        const next = normalizeDocument(value);

        if (JSON.stringify(next) !== JSON.stringify(editor.getJSON())) {
            editor.commands.setContent(next, { emitUpdate: false });
        }
    }, [editor, value]);

    useEffect(() => {
        if (editor && !editor.isDestroyed && editor.isEditable === disabled) {
            editor.setEditable(!disabled, false);
        }
    }, [editor, disabled]);

    const active = useEditorState({
        editor,
        selector: ({ editor: current }) => selectActiveState(current),
    });

    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [imageDialogOpen, setImageDialogOpen] = useState(false);
    /** The picker chunk is fetched on first use and then stays mounted. */
    const [imageDialogLoaded, setImageDialogLoaded] = useState(false);
    const [linkUrl, setLinkUrl] = useState('');
    const [linkError, setLinkError] = useState<string | undefined>();

    function openLinkDialog(current: Editor) {
        const href: unknown = current.getAttributes('link').href;
        setLinkUrl(typeof href === 'string' ? href : '');
        setLinkError(undefined);
        setLinkDialogOpen(true);
    }

    function submitLink() {
        if (!editor) {
            return;
        }

        const href = linkUrl.trim();

        if (href === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            setLinkDialogOpen(false);
            return;
        }

        if (!isAllowedRichTextHref(href)) {
            setLinkError(labels.linkInvalid);
            return;
        }

        editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
        setLinkDialogOpen(false);
    }

    const items: ToolbarItem[][] = [
        [
            {
                key: 'heading2',
                icon: Heading2,
                pressed: active?.heading2 ?? false,
                shortcut: 'Control+Alt+2 Meta+Alt+2',
                run: (current) =>
                    current.chain().focus().toggleHeading({ level: 2 }).run(),
            },
            {
                key: 'heading3',
                icon: Heading3,
                pressed: active?.heading3 ?? false,
                shortcut: 'Control+Alt+3 Meta+Alt+3',
                run: (current) =>
                    current.chain().focus().toggleHeading({ level: 3 }).run(),
            },
            {
                key: 'heading4',
                icon: Heading4,
                pressed: active?.heading4 ?? false,
                shortcut: 'Control+Alt+4 Meta+Alt+4',
                run: (current) =>
                    current.chain().focus().toggleHeading({ level: 4 }).run(),
            },
        ],
        [
            {
                key: 'bold',
                icon: Bold,
                pressed: active?.bold ?? false,
                shortcut: 'Control+B Meta+B',
                run: (current) => current.chain().focus().toggleBold().run(),
            },
            {
                key: 'italic',
                icon: Italic,
                pressed: active?.italic ?? false,
                shortcut: 'Control+I Meta+I',
                run: (current) => current.chain().focus().toggleItalic().run(),
            },
            {
                key: 'strike',
                icon: Strikethrough,
                pressed: active?.strike ?? false,
                shortcut: 'Control+Shift+S Meta+Shift+S',
                run: (current) => current.chain().focus().toggleStrike().run(),
            },
            {
                key: 'code',
                icon: Code,
                pressed: active?.code ?? false,
                shortcut: 'Control+E Meta+E',
                run: (current) => current.chain().focus().toggleCode().run(),
            },
        ],
        [
            {
                key: 'bulletList',
                icon: List,
                pressed: active?.bulletList ?? false,
                shortcut: 'Control+Shift+8 Meta+Shift+8',
                run: (current) =>
                    current.chain().focus().toggleBulletList().run(),
            },
            {
                key: 'orderedList',
                icon: ListOrdered,
                pressed: active?.orderedList ?? false,
                shortcut: 'Control+Shift+7 Meta+Shift+7',
                run: (current) =>
                    current.chain().focus().toggleOrderedList().run(),
            },
            {
                key: 'blockquote',
                icon: Quote,
                pressed: active?.blockquote ?? false,
                shortcut: 'Control+Shift+B Meta+Shift+B',
                run: (current) =>
                    current.chain().focus().toggleBlockquote().run(),
            },
            {
                key: 'horizontalRule',
                icon: Minus,
                run: (current) =>
                    current.chain().focus().setHorizontalRule().run(),
            },
        ],
        [
            {
                key: 'link',
                icon: Link2,
                opensDialog: true,
                run: openLinkDialog,
            },
            {
                key: 'unlink',
                icon: Unlink,
                disabled: !(active?.link ?? false),
                run: (current) =>
                    current
                        .chain()
                        .focus()
                        .extendMarkRange('link')
                        .unsetLink()
                        .run(),
            },
        ],
        ...(imagePicker
            ? [
                  [
                      {
                          key: 'image' as const,
                          icon: ImagePlus,
                          opensDialog: true,
                          run: () => {
                              setImageDialogLoaded(true);
                              setImageDialogOpen(true);
                          },
                      },
                  ],
              ]
            : []),
    ];

    const toolbarDisabled = disabled || !editor;
    const enabledKeys = items
        .flat()
        .filter((item) => !item.disabled)
        .map((item) => item.key);
    const [focusKey, setFocusKey] = useState<ToolKey>('heading2');
    const tabStopKey = enabledKeys.includes(focusKey)
        ? focusKey
        : enabledKeys[0];
    const toolbarRef = useRef<HTMLDivElement>(null);

    function handleToolbarKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        const buttons = Array.from(
            toolbarRef.current?.querySelectorAll<HTMLButtonElement>(
                'button:not(:disabled)',
            ) ?? [],
        );
        const current = buttons.indexOf(
            document.activeElement as HTMLButtonElement,
        );

        if (buttons.length === 0 || current === -1) {
            return;
        }

        const targets: Record<string, number> = {
            ArrowRight: (current + 1) % buttons.length,
            ArrowLeft: (current - 1 + buttons.length) % buttons.length,
            Home: 0,
            End: buttons.length - 1,
        };
        const target = targets[event.key];

        if (target !== undefined) {
            event.preventDefault();
            buttons[target]?.focus();
        }
    }

    return (
        <>
            <div className={richTextFrame({ invalid, disabled })}>
                <div
                    ref={toolbarRef}
                    role="toolbar"
                    aria-label={labels.toolbar}
                    aria-controls={id}
                    onKeyDown={handleToolbarKeyDown}
                    className="border-input bg-surface-subtle flex flex-wrap items-center gap-1 border-b p-1"
                >
                    {items.map((group, groupIndex) => (
                        <div key={groupIndex} className="flex items-center">
                            {groupIndex > 0 && (
                                <span
                                    aria-hidden="true"
                                    className="bg-border mr-1 h-6 w-px"
                                />
                            )}
                            {group.map((item) => {
                                const Icon = item.icon;
                                const itemLabel =
                                    item.key === 'image'
                                        ? (imagePicker?.labels.image ?? '')
                                        : labels[item.key];

                                return (
                                    <button
                                        key={item.key}
                                        type="button"
                                        aria-label={itemLabel}
                                        title={itemLabel}
                                        aria-pressed={item.pressed}
                                        aria-haspopup={
                                            item.opensDialog
                                                ? 'dialog'
                                                : undefined
                                        }
                                        aria-keyshortcuts={item.shortcut}
                                        tabIndex={
                                            item.key === tabStopKey ? 0 : -1
                                        }
                                        disabled={
                                            toolbarDisabled || item.disabled
                                        }
                                        onFocus={() => setFocusKey(item.key)}
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                        onClick={() => {
                                            if (editor) {
                                                item.run(editor);
                                            }
                                        }}
                                        className={cn(
                                            'text-foreground inline-flex size-11 items-center justify-center rounded-md transition-colors',
                                            'hover:bg-accent hover:text-accent-foreground',
                                            'focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none',
                                            'disabled:pointer-events-none disabled:opacity-50',
                                            'aria-pressed:bg-accent aria-pressed:text-accent-foreground',
                                        )}
                                    >
                                        <Icon
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>
                {editor ? (
                    <EditorContent editor={editor} />
                ) : (
                    <div className="px-3 py-2">
                        <Skeleton shape="text" lines={3} />
                    </div>
                )}
            </div>
            <FormDialog
                open={linkDialogOpen}
                onOpenChange={setLinkDialogOpen}
                title={labels.linkDialogTitle}
                submitLabel={labels.linkSubmit}
                cancelLabel={labels.linkCancel}
                closeLabel={labels.linkClose}
                onSubmit={submitLink}
            >
                <TextField
                    name={`${name}-link-url`}
                    type="url"
                    label={labels.linkUrlLabel}
                    description={labels.linkUrlHint}
                    value={linkUrl}
                    onChange={(next) => {
                        setLinkUrl(next);
                        setLinkError(undefined);
                    }}
                    error={linkError}
                    autoComplete="url"
                />
            </FormDialog>
            {imagePicker && imageDialogLoaded && (
                <Suspense fallback={null}>
                    <RichTextImageDialog
                        open={imageDialogOpen}
                        onOpenChange={setImageDialogOpen}
                        name={name}
                        picker={imagePicker}
                        onInsert={({ mediaId, alt }) => {
                            editor
                                ?.chain()
                                .focus()
                                .insertContent({
                                    type: 'image',
                                    attrs: { mediaId, alt },
                                })
                                .run();
                        }}
                    />
                </Suspense>
            )}
        </>
    );
}
