import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import {
    Badge,
    Button,
    Heading,
    Icon,
    Inline,
    ResourceForm,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { update as updatePublication } from '@/routes/admin/system/publication';
import { store as sendTestMail } from '@/routes/admin/system/test-mail';

type PublicationValues = {
    coming_soon: boolean;
    noindex: boolean;
};

export type SiteLaunchPanelProps = {
    launch: App.Data.Admin.System.SiteLaunchData;
};

/**
 * Launch tools of the System page: the coming soon and site-wide noindex
 * switches, the launch checklist (each item done or to do, never colour
 * alone) and the test e-mail to the signed-in administrator.
 */
export function SiteLaunchPanel({ launch }: SiteLaunchPanelProps) {
    const { t } = useTranslation();
    const [isSendingMail, setIsSendingMail] = useState(false);
    const form = useForm<PublicationValues>({
        coming_soon: launch.comingSoon,
        noindex: launch.noindex,
    });

    function sendMail() {
        router.post(
            sendTestMail.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => setIsSendingMail(true),
                onFinish: () => setIsSendingMail(false),
            },
        );
    }

    return (
        <Surface tone="default" padding="default" radius="default" border>
            <Stack gap="relaxed">
                <Stack gap="tight">
                    <Heading level={2} variant="group">
                        {t('admin.launch.title')}
                    </Heading>
                    <Text variant="caption" tone="muted">
                        {t('admin.launch.description')}
                    </Text>
                </Stack>

                <ResourceForm<PublicationValues>
                    sections={[
                        {
                            id: 'publication',
                            title: t('admin.launch.publicationTitle'),
                            fields: [
                                {
                                    type: 'switch',
                                    name: 'coming_soon',
                                    label: t('admin.launch.comingSoon'),
                                    hint: t('admin.launch.comingSoonHelp'),
                                },
                                {
                                    type: 'switch',
                                    name: 'noindex',
                                    label: t('admin.launch.noindex'),
                                    hint: t('admin.launch.noindexHelp'),
                                },
                            ],
                        },
                    ]}
                    values={form.data}
                    errors={form.errors}
                    onChange={(name, value) =>
                        form.setData((data) => ({ ...data, [name]: value }))
                    }
                    onSubmit={() =>
                        form.submit(updatePublication(), {
                            preserveScroll: true,
                            onSuccess: () => form.setDefaults(),
                        })
                    }
                    isPending={form.processing}
                    recentlySuccessful={form.recentlySuccessful}
                    labels={{
                        submit: t('admin.launch.savePublication'),
                        errorSummaryTitle: t('admin.launch.errorSummaryTitle'),
                        saved: t('admin.launch.publicationSaved'),
                    }}
                />

                <Stack gap="default">
                    <Heading level={3} variant="group">
                        {t('admin.launch.checklistTitle')}
                    </Heading>
                    <Stack gap="tight">
                        {launch.checklist.map((item) => (
                            <Inline
                                key={item.key}
                                gap="default"
                                align="center"
                                justify="between"
                                wrap
                            >
                                <Text>
                                    {t(`admin.launch.checks.${item.key}`)}
                                </Text>
                                <Badge tone={item.done ? 'success' : 'danger'}>
                                    <Inline gap="tight" align="center">
                                        <Icon
                                            icon={
                                                item.done
                                                    ? CheckCircle2
                                                    : AlertTriangle
                                            }
                                            size="sm"
                                        />
                                        {item.done
                                            ? t('admin.launch.done')
                                            : t('admin.launch.todo')}
                                    </Inline>
                                </Badge>
                            </Inline>
                        ))}
                    </Stack>
                    <Inline gap="default" align="center" wrap>
                        <Button
                            variant="outline"
                            onClick={sendMail}
                            isPending={isSendingMail}
                            disabled={isSendingMail}
                        >
                            {t('admin.launch.sendTestMail')}
                        </Button>
                        <Text variant="caption" tone="muted">
                            {t('admin.launch.testMailHelp', {
                                email: launch.testMailRecipient,
                            })}
                        </Text>
                    </Inline>
                </Stack>
            </Stack>
        </Surface>
    );
}
