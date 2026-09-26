import { Head, usePage } from '@inertiajs/react';
import {
    Activity,
    Database,
    HardDrive,
    Layers,
    Lock,
    Server,
    ShieldCheck,
} from 'lucide-react';
import {
    AdminLocaleSelect,
    Badge,
    Grid,
    Heading,
    Icon,
    PageHeader,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';

type SystemProps = {
    appName: string;
    environment: string;
    debugMode: boolean;
    phpVersion: string;
    laravelVersion: string;
    databaseDriver: string;
    cacheDriver: string;
    queueDriver: string;
};

type SecurityProps = {
    requireTwoFactorForAdmin: boolean;
    sessionLifetime: number;
    httpsEnabled: boolean;
};

type ModuleItem = {
    key: 'identity' | 'content' | 'media' | 'audit';
    status: 'active' | 'ready' | 'planned';
};

type AdminSettingsProps = {
    currentLocale: string;
    defaultAdminLocale: string;
    twoFactorEnforced: boolean;
};

type AdminPageProps = {
    system: SystemProps;
    security: SecurityProps;
    modules: ModuleItem[];
    adminSettings: AdminSettingsProps;
};

export default function AdminIndex() {
    const { system, security, modules, adminSettings } =
        usePage<AdminPageProps>().props;
    const { t } = useTranslation();

    const moduleIconMap = {
        identity: ShieldCheck,
        content: Layers,
        media: HardDrive,
        audit: Activity,
    };

    return (
        <>
            <Head title={t('admin.dashboard')} />

            <Stack gap="default">
                <PageHeader
                    badge={
                        <Badge tone="neutral">{t('admin.platformBadge')}</Badge>
                    }
                    title={system.appName}
                    description={t('admin.platformDescription')}
                    actions={
                        <>
                            <Badge
                                tone={
                                    system.environment === 'production'
                                        ? 'success'
                                        : 'primary'
                                }
                            >
                                {system.environment.toUpperCase()}
                            </Badge>
                            <Badge tone="outline">
                                PHP {system.phpVersion} / Laravel{' '}
                                {system.laravelVersion}
                            </Badge>
                        </>
                    }
                />

                <Surface
                    tone="default"
                    padding="default"
                    radius="default"
                    border
                >
                    <Stack gap="default">
                        <Stack gap="tight">
                            <Heading level={2} variant="subsection">
                                {t('admin.systemStatus.title')}
                            </Heading>
                            <Text variant="caption" tone="muted">
                                {t('admin.systemStatus.description')}
                            </Text>
                        </Stack>

                        <Grid layout="cards" gap="tight">
                            <Surface
                                tone="subtle"
                                padding="compact"
                                radius="default"
                                border={false}
                            >
                                <Stack gap="tight">
                                    <Icon
                                        icon={Server}
                                        size="default"
                                        tone="primary"
                                    />
                                    <Text variant="caption" tone="muted">
                                        {t('admin.systemStatus.environment')}
                                    </Text>
                                    <Text variant="label" tone="default">
                                        {system.environment}
                                    </Text>
                                    <Badge
                                        tone={
                                            system.debugMode
                                                ? 'outline'
                                                : 'neutral'
                                        }
                                    >
                                        {system.debugMode
                                            ? t(
                                                  'admin.systemStatus.debugEnabled',
                                              )
                                            : t(
                                                  'admin.systemStatus.debugDisabled',
                                              )}
                                    </Badge>
                                </Stack>
                            </Surface>

                            <Surface
                                tone="subtle"
                                padding="compact"
                                radius="default"
                                border={false}
                            >
                                <Stack gap="tight">
                                    <Icon
                                        icon={Database}
                                        size="default"
                                        tone="primary"
                                    />
                                    <Text variant="caption" tone="muted">
                                        {t('admin.systemStatus.database')}
                                    </Text>
                                    <Text variant="label" tone="default">
                                        {system.databaseDriver}
                                    </Text>
                                    <Badge tone="neutral">
                                        {system.databaseDriver.toUpperCase()}
                                    </Badge>
                                </Stack>
                            </Surface>

                            <Surface
                                tone="subtle"
                                padding="compact"
                                radius="default"
                                border={false}
                            >
                                <Stack gap="tight">
                                    <Icon
                                        icon={Activity}
                                        size="default"
                                        tone="primary"
                                    />
                                    <Text variant="caption" tone="muted">
                                        {t('admin.systemStatus.cache')}
                                    </Text>
                                    <Text variant="label" tone="default">
                                        {system.cacheDriver}
                                    </Text>
                                    <Badge tone="neutral">
                                        {t('admin.systemStatus.queue')}:{' '}
                                        {system.queueDriver}
                                    </Badge>
                                </Stack>
                            </Surface>
                        </Grid>
                    </Stack>
                </Surface>

                <Surface
                    tone="default"
                    padding="default"
                    radius="default"
                    border
                >
                    <Stack gap="default">
                        <Stack gap="tight">
                            <Heading level={2} variant="subsection">
                                {t('admin.security.title')}
                            </Heading>
                            <Text variant="caption" tone="muted">
                                {t('admin.security.description')}
                            </Text>
                        </Stack>

                        <Grid layout="cards" gap="tight">
                            <Surface
                                tone="subtle"
                                padding="compact"
                                radius="default"
                                border={false}
                            >
                                <Stack gap="tight">
                                    <Icon
                                        icon={Lock}
                                        size="default"
                                        tone="primary"
                                    />
                                    <Text variant="caption" tone="muted">
                                        {t('admin.security.admin2fa')}
                                    </Text>
                                    <Badge
                                        tone={
                                            security.requireTwoFactorForAdmin
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {security.requireTwoFactorForAdmin
                                            ? t('admin.security.enforced')
                                            : t('admin.security.optional')}
                                    </Badge>
                                </Stack>
                            </Surface>

                            <Surface
                                tone="subtle"
                                padding="compact"
                                radius="default"
                                border={false}
                            >
                                <Stack gap="tight">
                                    <Text variant="caption" tone="muted">
                                        {t('admin.security.sessionLifetime')}
                                    </Text>
                                    <Text variant="label" tone="default">
                                        {t('admin.security.sessionMinutes', {
                                            minutes: security.sessionLifetime,
                                        })}
                                    </Text>
                                    <Badge tone="neutral">
                                        {security.httpsEnabled
                                            ? t('admin.security.httpsActive')
                                            : t('admin.security.httpsInactive')}
                                    </Badge>
                                </Stack>
                            </Surface>

                            <Surface
                                tone="subtle"
                                padding="compact"
                                radius="default"
                                border={false}
                            >
                                <Stack gap="tight">
                                    <Text variant="caption" tone="muted">
                                        {t(
                                            'admin.platformSettings.fallbackLabel',
                                        )}
                                    </Text>
                                    <Text variant="label" tone="default">
                                        {adminSettings.defaultAdminLocale.toUpperCase()}
                                    </Text>
                                    <Badge tone="outline">
                                        {t(
                                            'admin.platformSettings.fallbackBadge',
                                        )}
                                    </Badge>
                                </Stack>
                            </Surface>
                        </Grid>
                    </Stack>
                </Surface>

                <Surface
                    tone="default"
                    padding="default"
                    radius="default"
                    border
                >
                    <Stack gap="default">
                        <Stack gap="tight">
                            <Heading level={2} variant="subsection">
                                {t('admin.platformSettings.title')}
                            </Heading>
                            <Text variant="caption" tone="muted">
                                {t('admin.platformSettings.description')}
                            </Text>
                        </Stack>

                        <Surface
                            tone="subtle"
                            padding="compact"
                            radius="default"
                            border={false}
                        >
                            <Stack gap="tight">
                                <Text variant="label" tone="default">
                                    {t('admin.platformSettings.localeLabel')}
                                </Text>
                                <Text variant="caption" tone="muted">
                                    {t('admin.platformSettings.localeHelp')}
                                </Text>
                                <AdminLocaleSelect />
                            </Stack>
                        </Surface>
                    </Stack>
                </Surface>

                <Stack gap="tight">
                    <Heading level={2} variant="subsection">
                        {t('admin.modules.title')}
                    </Heading>
                    <Text variant="caption" tone="muted">
                        {t('admin.modules.description')}
                    </Text>

                    <Grid layout="cards" gap="tight">
                        {modules.map((item) => {
                            const ModuleIcon = moduleIconMap[item.key];
                            const statusTone =
                                item.status === 'active'
                                    ? 'success'
                                    : item.status === 'ready'
                                      ? 'primary'
                                      : 'neutral';

                            return (
                                <Surface
                                    key={item.key}
                                    tone="default"
                                    padding="compact"
                                    radius="default"
                                    border
                                >
                                    <Stack gap="tight">
                                        <Icon
                                            icon={ModuleIcon}
                                            size="lg"
                                            tone="primary"
                                        />
                                        <Heading level={3} variant="group">
                                            {t(
                                                `admin.modules.${item.key}.title`,
                                            )}
                                        </Heading>
                                        <Text variant="caption" tone="muted">
                                            {t(
                                                `admin.modules.${item.key}.description`,
                                            )}
                                        </Text>
                                        <Badge tone={statusTone}>
                                            {t(
                                                `admin.modules.status.${item.status}`,
                                            )}
                                        </Badge>
                                    </Stack>
                                </Surface>
                            );
                        })}
                    </Grid>
                </Stack>
            </Stack>
        </>
    );
}

AdminIndex.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
    ],
};
