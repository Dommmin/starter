import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Button,
    Card,
    CardContent,
    ConfirmDialog,
    RecordDetails,
    Stack,
    Text,
    TextareaField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { destroy, index, retry } from '@/routes/admin/contact';
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

            <Stack gap="default">
                <RecordDetails
                    title={t('admin.contact.showTitle')}
                    description={t('admin.contact.showDescription')}
                    emptyValuePlaceholder="—"
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
