import { Head, router, useForm, useHttp, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Alert,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    ConfirmDialog,
    Inline,
    PageHeader,
    RecordDetails,
    SelectField,
    Stack,
    Text,
    TextField,
    TextareaField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { contactReply } from '@/routes/admin/assistant';
import { destroy, index, retry, update } from '@/routes/admin/contact';
import { ContactStatusBadge } from './status-badge';

type ShowProps = App.Data.Admin.Contact.ContactMessageShowData;

/**
 * Details of one contact message; administrators may re-queue a failed
 * delivery or delete the message.
 */
export default function AdminContactShow() {
    const { contactMessage, can } = usePage<ShowProps>().props;
    const { t, formatDate } = useTranslation();
    const [isRetrying, setIsRetrying] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [guidance, setGuidance] = useState('');
    const [replyError, setReplyError] = useState<string | null>(null);
    const [replyDraft, setReplyDraft] = useState<{
        subject: string;
        body: string;
    } | null>(null);

    const workflowForm = useForm({
        lead_status: contactMessage.leadStatus,
        internal_notes: contactMessage.internalNotes ?? '',
    });

    const replyRequest = useHttp<
        { contact_message_id: number; guidance: string },
        { subject: string; body: string }
    >({
        contact_message_id: contactMessage.id,
        guidance: '',
    });

    function saveWorkflow(event: React.FormEvent) {
        event.preventDefault();
        workflowForm.patch(update.url(contactMessage.id), {
            preserveScroll: true,
        });
    }

    async function generateReply() {
        setReplyError(null);
        replyRequest.setData('guidance', guidance);
        try {
            const response = await replyRequest.post(contactReply.url());
            if (response && typeof response.body === 'string') {
                setReplyDraft(response);
            }
        } catch {
            setReplyError(t('admin.contact.aiReplyError'));
        }
    }

    function retryDelivery() {
        setIsRetrying(true);
        router.post(
            retry.url(contactMessage.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsRetrying(false),
            },
        );
    }

    function confirmDelete() {
        setIsDeleting(true);
        router.delete(destroy.url(contactMessage.id), {
            onFinish: () => setIsDeleting(false),
        });
    }

    const paragraphs = contactMessage.message.split(/\r?\n/);

    return (
        <>
            <Head title={t('admin.contact.showTitle')} />

            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.contact.showTitle')}
                    description={t('admin.contact.showDescription')}
                    actions={
                        can.retry || can.delete ? (
                            <>
                                {can.retry && (
                                    <Button
                                        variant="outline"
                                        onClick={retryDelivery}
                                        isPending={isRetrying}
                                    >
                                        {t('admin.contact.actionRetry')}
                                    </Button>
                                )}
                                {can.delete && (
                                    <Button
                                        variant="destructive"
                                        onClick={() => setIsDeleteOpen(true)}
                                    >
                                        {t('admin.contact.actionDelete')}
                                    </Button>
                                )}
                            </>
                        ) : undefined
                    }
                />

                <RecordDetails
                    title={contactMessage.name}
                    emptyValuePlaceholder="—"
                    items={[
                        {
                            id: 'name',
                            label: t('admin.contact.fields.name'),
                            value: contactMessage.name,
                        },
                        {
                            id: 'email',
                            label: t('admin.contact.fields.email'),
                            value: contactMessage.email,
                        },
                        {
                            id: 'locale',
                            label: t('admin.contact.fields.locale'),
                            value: contactMessage.locale,
                        },
                        {
                            id: 'status',
                            label: t('admin.contact.fields.status'),
                            value: (
                                <ContactStatusBadge
                                    status={contactMessage.status}
                                />
                            ),
                        },
                        {
                            id: 'attempts',
                            label: t('admin.contact.fields.attempts'),
                            value: contactMessage.attempts,
                        },
                        {
                            id: 'createdAt',
                            label: t('admin.contact.fields.createdAt'),
                            value: contactMessage.createdAt
                                ? formatDate(contactMessage.createdAt)
                                : null,
                        },
                        {
                            id: 'sentAt',
                            label: t('admin.contact.fields.sentAt'),
                            value: contactMessage.sentAt
                                ? formatDate(contactMessage.sentAt)
                                : null,
                        },
                        ...(contactMessage.formTitle
                            ? [
                                  {
                                      id: 'form',
                                      label: t('admin.contact.fields.form'),
                                      value: contactMessage.formTitle,
                                  },
                              ]
                            : []),
                        ...contactMessage.extraFields.map(
                            (field, position) => ({
                                id: `extra-${position}`,
                                label: field.label,
                                value: field.value,
                            }),
                        ),
                        ...(contactMessage.webhookStatus
                            ? [
                                  {
                                      id: 'webhook',
                                      label: t('admin.contact.fields.webhook'),
                                      value: t('admin.contact.webhookStatus', {
                                          status: t(
                                              `admin.contact.webhookStatuses.${contactMessage.webhookStatus}`,
                                          ),
                                          attempts:
                                              contactMessage.webhookAttempts,
                                      }),
                                  },
                              ]
                            : []),
                        {
                            id: 'message',
                            label: t('admin.contact.fields.message'),
                            value: (
                                <Stack gap="tight">
                                    {paragraphs.map((paragraph, position) => (
                                        <Text key={position}>
                                            {paragraph || ' '}
                                        </Text>
                                    ))}
                                </Stack>
                            ),
                        },
                    ]}
                />

                {contactMessage.lastError && (
                    <Card>
                        <CardContent>
                            <TextareaField
                                name="last_error"
                                label={t('admin.contact.fields.lastError')}
                                description={t(
                                    'admin.contact.fields.lastErrorHelp',
                                )}
                                value={contactMessage.lastError}
                                rows={3}
                                readOnly
                            />
                        </CardContent>
                    </Card>
                )}

                {can.update && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('admin.contact.workflowTitle')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={saveWorkflow}>
                                <Stack gap="default">
                                    <SelectField
                                        name="lead_status"
                                        label={t('admin.contact.leadStatus')}
                                        value={workflowForm.data.lead_status}
                                        onChange={(value) =>
                                            workflowForm.setData(
                                                'lead_status',
                                                value as App.Enums.LeadStatus,
                                            )
                                        }
                                        options={[
                                            {
                                                value: 'new',
                                                label: t(
                                                    'admin.contact.leadStatuses.new',
                                                ),
                                            },
                                            {
                                                value: 'in_progress',
                                                label: t(
                                                    'admin.contact.leadStatuses.in_progress',
                                                ),
                                            },
                                            {
                                                value: 'replied',
                                                label: t(
                                                    'admin.contact.leadStatuses.replied',
                                                ),
                                            },
                                            {
                                                value: 'archived',
                                                label: t(
                                                    'admin.contact.leadStatuses.archived',
                                                ),
                                            },
                                        ]}
                                        error={workflowForm.errors.lead_status}
                                    />

                                    <TextareaField
                                        name="internal_notes"
                                        label={t('admin.contact.internalNotes')}
                                        value={workflowForm.data.internal_notes}
                                        onChange={(value) =>
                                            workflowForm.setData(
                                                'internal_notes',
                                                value,
                                            )
                                        }
                                        rows={3}
                                        error={
                                            workflowForm.errors.internal_notes
                                        }
                                    />

                                    <Inline>
                                        <Button
                                            type="submit"
                                            isPending={workflowForm.processing}
                                        >
                                            {t('admin.contact.saveWorkflow')}
                                        </Button>
                                    </Inline>
                                </Stack>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {can.suggestReply && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('admin.contact.aiReplyTitle')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Stack gap="default">
                                {replyError && (
                                    <Alert tone="danger" title={replyError} />
                                )}

                                <TextareaField
                                    name="ai_reply_guidance"
                                    label={t('admin.contact.aiReplyGuidance')}
                                    value={guidance}
                                    onChange={setGuidance}
                                    rows={2}
                                />

                                <Inline>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={generateReply}
                                        isPending={replyRequest.processing}
                                    >
                                        {t('admin.contact.aiReplyGenerate')}
                                    </Button>
                                </Inline>

                                {replyDraft && (
                                    <Stack gap="tight">
                                        <TextField
                                            name="ai_reply_subject"
                                            label={t(
                                                'admin.contact.aiReplySubject',
                                            )}
                                            value={replyDraft.subject}
                                            onChange={(subject) =>
                                                setReplyDraft((prev) =>
                                                    prev
                                                        ? { ...prev, subject }
                                                        : prev,
                                                )
                                            }
                                        />
                                        <TextareaField
                                            name="ai_reply_body"
                                            label={t(
                                                'admin.contact.aiReplyBody',
                                            )}
                                            value={replyDraft.body}
                                            onChange={(body) =>
                                                setReplyDraft((prev) =>
                                                    prev
                                                        ? { ...prev, body }
                                                        : prev,
                                                )
                                            }
                                            rows={6}
                                        />
                                    </Stack>
                                )}
                            </Stack>
                        </CardContent>
                    </Card>
                )}
            </Stack>

            <ConfirmDialog
                open={isDeleteOpen}
                onOpenChange={setIsDeleteOpen}
                title={t('admin.contact.deleteTitle')}
                description={t('admin.contact.deleteDescription')}
                confirmLabel={t('admin.contact.deleteConfirm')}
                cancelLabel={t('admin.contact.cancel')}
                closeLabel={t('actions.close')}
                tone="destructive"
                isPending={isDeleting}
                onConfirm={confirmDelete}
            />
        </>
    );
}

AdminContactShow.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.contact.title', href: index() },
    ],
};
