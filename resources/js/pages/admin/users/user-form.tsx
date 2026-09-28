import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    ConfirmDialog,
    ConflictDialog,
    PageHeader,
    ResourceForm,
    Stack,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { destroy, edit, index, store, update } from '@/routes/admin/users';

type EditorProps = App.Data.Admin.Users.UserEditorData;
type RecordData = App.Data.Admin.Users.UserFormData;

/** Editable form state; keys are the request field names (`''` role = none). */
type FormValues = {
    name: string;
    email: string;
    role: string;
};

const FIELD_NAMES: string[] = ['name', 'email', 'role'];

function toValues(record: RecordData): FormValues {
    return {
        name: record.name,
        email: record.email,
        role: record.role ?? '',
    };
}

export type UserFormProps = {
    editor: EditorProps;
};

/**
 * Shared create/edit screen of an account: name, email and panel role in
 * one `ResourceForm`, read-only security status, optimistic-lock conflict
 * handling and deletion. Mutations may first ask for the password
 * (423 handled globally by PasswordConfirmationModal).
 */
export function UserForm({ editor }: UserFormProps) {
    const record = editor.user;
    const recordId = record.id;
    const { can } = editor;
    const { t } = useTranslation();
    const form = useForm<FormValues>(toValues(record));
    const [isConflictOpen, setIsConflictOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const errors = form.errors as Partial<Record<string, string>>;
    /** Errors that no field renders inline (e.g. `updated_at`). */
    const generalErrors = Object.keys(errors)
        .filter((key) => key !== 'conflict' && !FIELD_NAMES.includes(key))
        .map((key) => errors[key] ?? '')
        .filter((message) => message !== '');

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

            if (!window.confirm(t('admin.users.unsavedChanges'))) {
                event.preventDefault();
            }
        });
    }, [form.isDirty, t]);

    function submit() {
        form.transform((data) => ({
            ...data,
            ...(recordId !== null
                ? { updated_at: record.updatedAt ?? '' }
                : {}),
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
        setDeleteError(null);
        router.delete(destroy.url(recordId), {
            onError: (received) => {
                setIsDeleteOpen(false);
                setDeleteError(Object.values(received)[0] ?? null);
            },
            onFinish: () => setIsDeleting(false),
        });
    }

    const roleOptions = [
        { value: '', label: t('admin.users.role.none') },
        { value: 'editor', label: t('admin.users.role.editor') },
        { value: 'admin', label: t('admin.users.role.admin') },
    ];

    return (
        <Stack gap="relaxed">
            <PageHeader
                title={
                    recordId === null
                        ? t('admin.users.createTitle')
                        : t('admin.users.editTitle')
                }
                description={t('admin.users.description')}
                actions={
                    recordId !== null && can.delete ? (
                        <Button
                            variant="destructive"
                            onClick={() => setIsDeleteOpen(true)}
                        >
                            {t('admin.users.delete')}
                        </Button>
                    ) : undefined
                }
            />

            {recordId !== null && (
                <Stack gap="tight" align="start">
                    <Badge tone={record.emailVerified ? 'success' : 'neutral'}>
                        {record.emailVerified
                            ? t('admin.users.emailVerified')
                            : t('admin.users.emailNotVerified')}
                    </Badge>
                    <Badge
                        tone={record.twoFactorEnabled ? 'success' : 'neutral'}
                    >
                        {record.twoFactorEnabled
                            ? t('admin.users.twoFactorOn')
                            : t('admin.users.twoFactorOff')}
                    </Badge>
                </Stack>
            )}

            {deleteError && <Alert tone="danger" title={deleteError} />}

            {generalErrors.length > 0 && (
                <Alert
                    tone="danger"
                    title={t('admin.users.errorSummaryTitle')}
                    description={generalErrors.join(' ')}
                />
            )}

            <ResourceForm<FormValues>
                values={form.data}
                errors={errors}
                onChange={(name, value) =>
                    form.setData((data) => ({ ...data, [name]: value }))
                }
                onSubmit={submit}
                isPending={form.processing}
                cancelHref={index()}
                labels={{
                    submit: form.processing
                        ? t('admin.users.saving')
                        : t('admin.users.save'),
                    cancel: t('admin.users.cancel'),
                    errorSummaryTitle: t('admin.users.errorSummaryTitle'),
                }}
                sections={[
                    {
                        id: 'account',
                        title: t('admin.users.accountSection'),
                        description: t('admin.users.accountDescription'),
                        fields: [
                            {
                                type: 'text',
                                name: 'name',
                                label: t('admin.users.fields.name'),
                                autoComplete: 'off',
                                required: true,
                            },
                            {
                                type: 'text',
                                name: 'email',
                                inputType: 'email',
                                label: t('admin.users.fields.email'),
                                hint:
                                    recordId === null
                                        ? t('admin.users.fields.emailHelp')
                                        : t(
                                              'admin.users.fields.emailChangeHelp',
                                          ),
                                autoComplete: 'off',
                                required: true,
                            },
                        ],
                    },
                    {
                        id: 'access',
                        title: t('admin.users.accessSection'),
                        description: t('admin.users.accessDescription'),
                        fields: [
                            {
                                type: 'select',
                                name: 'role',
                                label: t('admin.users.fields.role'),
                                options: roleOptions,
                                disabled: !can.changeRole,
                                hint: can.changeRole
                                    ? undefined
                                    : t('admin.users.fields.ownRoleHelp'),
                            },
                        ],
                    },
                ]}
            />

            {recordId !== null && (
                <ConflictDialog
                    open={isConflictOpen}
                    onOpenChange={setIsConflictOpen}
                    title={t('admin.users.conflictTitle')}
                    description={t('admin.users.conflictDescription')}
                    reloadLabel={t('admin.users.conflictReload')}
                    onReload={reloadLatest}
                    overwriteLabel={t('admin.users.cancel')}
                    onOverwrite={() => setIsConflictOpen(false)}
                />
            )}

            {recordId !== null && can.delete && (
                <ConfirmDialog
                    open={isDeleteOpen}
                    onOpenChange={setIsDeleteOpen}
                    title={t('admin.users.deleteTitle')}
                    description={t('admin.users.deleteDescription')}
                    confirmLabel={t('admin.users.deleteConfirm')}
                    cancelLabel={t('admin.users.cancel')}
                    closeLabel={t('actions.close')}
                    tone="destructive"
                    isPending={isDeleting}
                    onConfirm={confirmDelete}
                />
            )}
        </Stack>
    );
}
