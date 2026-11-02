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
import { update as updateMaintenance } from '@/routes/admin/system/maintenance';
import { update as updatePublication } from '@/routes/admin/system/publication';
import { store as sendTestMail } from '@/routes/admin/system/test-mail';

type PublicationValues = {
    coming_soon: boolean;
    noindex: boolean;
};

type MaintenanceValues = {
    maintenance: boolean;
    duration: App.Enums.MaintenanceDuration;
};

const MAINTENANCE_DURATIONS: App.Enums.MaintenanceDuration[] = [
    'unknown',
    '15',
    '30',
    '60',
    '120',
    '240',
    '1440',
];

export type SiteLaunchPanelProps = {
    launch: App.Data.Admin.System.SiteLaunchData;
};

/**
 * Launch tools of the System page: the coming soon and site-wide noindex
 * switches, the maintenance switch with its expected duration, the launch checklist (each item done or to do, never colour
 * alone) and the test e-mail to the signed-in administrator.
 */
export function SiteLaunchPanel({ launch }: SiteLaunchPanelProps) {
    const { t, formatDate } = useTranslation();
    const [isSendingMail, setIsSendingMail] = useState(false);
    const form = useForm<PublicationValues>({
        coming_soon: launch.comingSoon,
        noindex: launch.noindex,
    });
    const maintenanceForm = useForm<MaintenanceValues>({
        maintenance: launch.maintenance,
        duration: 'unknown',
    });
    const maintenanceUntil =
        launch.maintenance && launch.maintenanceUntil
            ? t('admin.launch.maintenanceUntil', {
                  time: formatDate(launch.maintenanceUntil, {
                      dateStyle: 'medium',
                      timeStyle: 'short',
                  }),
              })
            : null;

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

                <ResourceForm<MaintenanceValues>
                    sections={[
                        {
                            id: 'maintenance',
                            title: t('admin.launch.maintenanceTitle'),
                            description: maintenanceUntil ?? undefined,
                            fields: [
                                {
                                    type: 'switch',
                                    name: 'maintenance',
                                    label: t('admin.launch.maintenance'),
                                    hint: t('admin.launch.maintenanceHelp'),
                                },
                                {
                                    type: 'select',
                                    name: 'duration',
                                    label: t(
                                        'admin.launch.maintenanceDuration',
                                    ),
                                    hint: t(
                                        'admin.launch.maintenanceDurationHelp',
                                    ),
                                    options: MAINTENANCE_DURATIONS.map(
                                        (value) => ({
                                            value,
                                            label: t(
                                                `admin.launch.durations.${value}`,
                                            ),
                                        }),
                                    ),
                                    disabled: !maintenanceForm.data.maintenance,
                                },
                            ],
                        },
                    ]}
                    values={maintenanceForm.data}
                    errors={maintenanceForm.errors}
                    onChange={(name, value) =>
                        maintenanceForm.setData((data) => ({
                            ...data,
                            [name]: value,
                        }))
                    }
                    onSubmit={() =>
                        maintenanceForm.submit(updateMaintenance(), {
                            preserveScroll: true,
                            onSuccess: () => maintenanceForm.setDefaults(),
                        })
                    }
                    isPending={maintenanceForm.processing}
                    recentlySuccessful={maintenanceForm.recentlySuccessful}
                    labels={{
                        submit: t('admin.launch.saveMaintenance'),
                        errorSummaryTitle: t('admin.launch.errorSummaryTitle'),
                        saved: t('admin.launch.maintenanceSaved'),
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
