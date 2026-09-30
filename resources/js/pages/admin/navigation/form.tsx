import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Alert,
    Button,
    ConfirmDialog,
    ConflictDialog,
    PageHeader,
    ResourceForm,
    type ResourceFormField,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { destroy, edit, index, store, update } from '@/routes/admin/navigation';

type EditorProps = App.Data.Admin.Navigation.MenuItemEditorData;
type ItemType = App.Enums.MenuItemType;

/**
 * Editable form state; keys are the request field names. Selects cannot
 * hold an empty option value, so "no parent" / "home page" use `NONE`.
 */
type FormValues = {
    type: string;
    parent_id: string;
    page_id: string;
    article_id: string;
    anchor: string;
    url: string;
    label: string;
    open_in_new_tab: boolean;
};

const NONE = 'none';

const TYPES: ItemType[] = [
    'page',
    'article',
    'article_index',
    'anchor',
    'external',
    'group',
];

const FIELD_NAMES: string[] = [
    'type',
    'parent_id',
    'page_id',
    'article_id',
    'anchor',
    'url',
    'label',
    'open_in_new_tab',
];

/** Types whose label may stay empty (the target title is used instead). */
const TITLED_TYPES: string[] = ['page', 'article'];

function toValues(
    item: App.Data.Admin.Navigation.MenuItemFormData,
): FormValues {
    return {
        type: item.type,
        parent_id: item.parentId === null ? NONE : String(item.parentId),
        page_id:
            item.pageId === null
                ? item.type === 'anchor'
                    ? NONE
                    : ''
                : String(item.pageId),
        article_id: item.articleId === null ? '' : String(item.articleId),
        anchor: item.anchor ?? '',
        url: item.url ?? '',
        label: item.label ?? '',
        open_in_new_tab: item.openInNewTab,
    };
}

/**
 * Request payload: fields that do not apply to the selected type are sent
 * empty, so switching the type never leaves a stale target behind.
 */
function toPayload(values: FormValues): Record<string, string | boolean> {
    const type = values.type;
    const pageId = values.page_id === NONE ? '' : values.page_id;

    return {
        type,
        parent_id:
            type === 'group' || values.parent_id === NONE
                ? ''
                : values.parent_id,
        page_id: type === 'page' || type === 'anchor' ? pageId : '',
        article_id: type === 'article' ? values.article_id : '',
        anchor: type === 'anchor' ? values.anchor : '',
        url: type === 'external' ? values.url : '',
        label: values.label,
        open_in_new_tab: type === 'external' && values.open_in_new_tab,
    };
}

/**
 * Keep the page select on a valid option when the type changes: an anchor
 * defaults to the home page, a page link to "not selected".
 */
function withTypeDefaults(values: FormValues): FormValues {
    if (values.type === 'anchor' && values.page_id === '') {
        return { ...values, page_id: NONE };
    }

    if (values.type === 'page' && values.page_id === NONE) {
        return { ...values, page_id: '' };
    }

    return values;
}

export type MenuItemFormProps = {
    editor: EditorProps;
};

/**
 * Shared create/edit screen of a menu item: the fields follow the selected
 * type, with optimistic-lock conflict handling and deletion.
 */
export function MenuItemForm({ editor }: MenuItemFormProps) {
    const { item, can } = editor;
    const recordId = item.id;
    const { t } = useTranslation();
    const form = useForm<FormValues>(toValues(item));
    const [isConflictOpen, setIsConflictOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const type = form.data.type;
    const errors = form.errors as Partial<Record<string, string>>;
    /** Errors that no field renders inline (e.g. `updated_at`). */
    const generalErrors = Object.keys(errors)
        .filter((key) => key !== 'conflict' && !FIELD_NAMES.includes(key))
        .map((key) => errors[key] ?? '')
        .filter((message) => message !== '');

    const localeName =
        editor.locales.available.find((locale) => locale.code === item.locale)
            ?.native ?? item.locale;
    const menuQuery = { location: item.location, locale: item.locale };

    useEffect(() => {
        if (!form.isDirty) {
            return;
        }

        return router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                visit.method !== 'get' ||
                visit.url.pathname === window.location.pathname
            ) {
                return;
            }

            if (!window.confirm(t('admin.navigation.unsavedChanges'))) {
                event.preventDefault();
            }
        });
    }, [form.isDirty, t]);

    function submit() {
        form.transform((data) => ({
            ...toPayload(data),
            ...(recordId === null
                ? menuQuery
                : { updated_at: item.updatedAt ?? '' }),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: (received: Partial<Record<string, string>>) => {
                if (received.conflict) {
                    setIsConflictOpen(true);
                }
            },
        };

        if (recordId === null) {
            form.submit(store(), options);
        } else {
            form.submit(update(recordId), options);
        }
    }

    function reloadLatest() {
        if (recordId === null) {
            return;
        }

        setIsConflictOpen(false);
        router.visit(edit.url(recordId), { preserveState: false });
    }

    function confirmDelete() {
        if (recordId === null) {
            return;
        }

        setIsDeleting(true);
        router.delete(destroy.url(recordId), {
            onFinish: () => setIsDeleting(false),
        });
    }

    const targetOption = (
        target: App.Data.Admin.Navigation.MenuTargetOptionData,
    ) => ({
        value: String(target.id),
        label: target.draft
            ? t('admin.navigation.draftOption', { title: target.title })
            : target.title,
    });

    const homeAnchorOption = (
        option: App.Data.Admin.Navigation.MenuAnchorOptionData,
    ) => {
        const section = t(`admin.homeSections.types.${option.sectionType}`);

        return {
            value: option.anchor,
            label: option.enabled
                ? section
                : t('admin.navigation.hiddenSectionOption', { section }),
        };
    };

    const fields: ResourceFormField<FormValues>[] = [
        {
            type: 'select',
            name: 'type',
            label: t('admin.navigation.fields.type'),
            required: true,
            options: TYPES.map((value) => ({
                value,
                label: t(`admin.navigation.types.${value}`),
            })),
        },
    ];

    if (type !== 'group') {
        fields.push({
            type: 'select',
            name: 'parent_id',
            label: t('admin.navigation.fields.parent'),
            disabled: item.hasChildren,
            hint: item.hasChildren
                ? t('admin.navigation.fields.parentLockedHint')
                : undefined,
            options: [
                { value: NONE, label: t('admin.navigation.fields.parentNone') },
                ...editor.parents.map((parent) => ({
                    value: String(parent.id),
                    label:
                        parent.label ??
                        t(`admin.navigation.types.${parent.type}`),
                })),
            ],
        });
    }

    if (type === 'page') {
        fields.push({
            type: 'select',
            name: 'page_id',
            label: t('admin.navigation.fields.page'),
            required: true,
            options: editor.pages.map(targetOption),
        });
    }

    if (type === 'article') {
        fields.push({
            type: 'select',
            name: 'article_id',
            label: t('admin.navigation.fields.article'),
            required: true,
            options: editor.articles.map(targetOption),
        });
    }

    if (type === 'anchor') {
        fields.push(
            {
                type: 'select',
                name: 'page_id',
                label: t('admin.navigation.fields.pageOptional'),
                options: [
                    {
                        value: NONE,
                        label: t('admin.navigation.fields.targetNone'),
                    },
                    ...editor.pages.map(targetOption),
                ],
            },
            form.data.page_id === NONE
                ? {
                      type: 'select',
                      name: 'anchor',
                      label: t('admin.navigation.fields.homeAnchor'),
                      hint: t('admin.navigation.fields.homeAnchorHint'),
                      required: true,
                      options: editor.homeAnchors.map(homeAnchorOption),
                  }
                : {
                      type: 'text',
                      name: 'anchor',
                      label: t('admin.navigation.fields.anchor'),
                      hint: t('admin.navigation.fields.anchorHint'),
                      required: true,
                  },
        );
    }

    if (type === 'external') {
        fields.push(
            {
                type: 'text',
                name: 'url',
                inputType: 'url',
                label: t('admin.navigation.fields.url'),
                hint: t('admin.navigation.fields.urlHint'),
                required: true,
            },
            {
                type: 'switch',
                name: 'open_in_new_tab',
                label: t('admin.navigation.fields.openInNewTab'),
            },
        );
    }

    const labelIsOptional = TITLED_TYPES.includes(type);
    fields.push({
        type: 'text',
        name: 'label',
        label: t('admin.navigation.fields.label'),
        required: !labelIsOptional,
        hint: labelIsOptional
            ? t('admin.navigation.fields.labelHint')
            : t('admin.navigation.fields.labelRequiredHint'),
    });

    return (
        <Stack gap="relaxed">
            <PageHeader
                title={
                    recordId === null
                        ? t('admin.navigation.createTitle')
                        : t('admin.navigation.editTitle')
                }
                description={t('admin.navigation.menuSummary', {
                    location: t(`admin.navigation.locations.${item.location}`),
                    locale: localeName,
                })}
                actions={
                    recordId !== null && can.delete ? (
                        <Button
                            variant="destructive"
                            onClick={() => setIsDeleteOpen(true)}
                        >
                            {t('admin.navigation.delete')}
                        </Button>
                    ) : undefined
                }
            />

            {item.targetMissing && (
                <Alert
                    tone="danger"
                    title={t('admin.navigation.targetMissing')}
                />
            )}

            {!item.targetMissing && item.draftTarget && (
                <Alert
                    tone="neutral"
                    title={t('admin.navigation.draftTarget')}
                />
            )}

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.navigation.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            <ResourceForm<FormValues>
                values={form.data}
                errors={errors}
                onChange={(name, value) =>
                    form.setData((data) =>
                        withTypeDefaults({ ...data, [name]: value }),
                    )
                }
                onSubmit={submit}
                isPending={form.processing}
                cancelHref={index({ query: menuQuery })}
                labels={{
                    submit: form.processing
                        ? t('admin.navigation.saving')
                        : t('admin.navigation.save'),
                    cancel: t('admin.navigation.cancel'),
                    errorSummaryTitle: t('admin.navigation.errorSummaryTitle'),
                }}
                sections={[
                    {
                        id: 'details',
                        title: t('admin.navigation.detailsTitle'),
                        fields,
                    },
                ]}
            />

            {recordId !== null && (
                <ConflictDialog
                    open={isConflictOpen}
                    onOpenChange={setIsConflictOpen}
                    title={t('admin.navigation.conflictTitle')}
                    description={t('admin.navigation.conflictDescription')}
                    reloadLabel={t('admin.navigation.conflictReload')}
                    onReload={reloadLatest}
                    overwriteLabel={t('admin.navigation.cancel')}
                    onOverwrite={() => setIsConflictOpen(false)}
                />
            )}

            {recordId !== null && can.delete && (
                <ConfirmDialog
                    open={isDeleteOpen}
                    onOpenChange={setIsDeleteOpen}
                    title={t('admin.navigation.confirmDelete.title')}
                    description={t(
                        'admin.navigation.confirmDelete.description',
                    )}
                    confirmLabel={t('admin.navigation.confirmDelete.confirm')}
                    cancelLabel={t('admin.navigation.cancel')}
                    closeLabel={t('actions.close')}
                    tone="destructive"
                    isPending={isDeleting}
                    onConfirm={confirmDelete}
                />
            )}
        </Stack>
    );
}
