import { Head, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Database,
    HardDrive,
    Layers,
    Lock,
    Server,
    ShieldCheck,
} from 'lucide-react';
import {
    Badge,
    Grid,
    Heading,
    Icon,
    Inline,
    Link,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as pagesIndex } from '@/routes/admin/pages';
import { index as systemIndex } from '@/routes/admin/system';

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
    defaultAdminLocale: string;
};

type SystemPageProps = {
    system: SystemProps;
    security: SecurityProps;
    modules: ModuleItem[];
    adminSettings: AdminSettingsProps;
};

/** A security setting: a check when safe, a warning sign when risky (never colour alone). */
function RiskBadge({ safe, label }: { safe: boolean; label: string }) {
    return (
        <Badge tone={safe ? 'success' : 'danger'}>
            <Inline gap="tight" align="center">
                <Icon icon={safe ? CheckCircle2 : AlertTriangle} size="sm" />
                {label}
            </Inline>
        </Badge>
    );
}

/**
 * Technical status for administrators: runtime, security policy and the
 * platform modules, kept off the dashboard so editors see their work first.
 */
export default function AdminSystem() {
    const { system, security, modules, adminSettings } =
        usePage<SystemPageProps>().props;
    const { t } = useTranslation();

    const moduleIconMap = {
        identity: ShieldCheck,
        content: Layers,
        media: HardDrive,
        audit: Activity,
    };

    return (
        <>
            <Head title={t('admin.platformBadge')} />

            <Stack gap="relaxed">
                <Stack gap="tight">
                    <Heading level={1} variant="page">
                        {t('admin.platformBadge')}
                    </Heading>
                    <Text tone="muted">{t('admin.platformDescription')}</Text>
                    <Text variant="caption" tone="muted">
                        {system.environment.toUpperCase()} · PHP{' '}
                        {system.phpVersion} / Laravel {system.laravelVersion}
                    </Text>
                </Stack>

                <Surface
                    tone="default"
                    padding="default"
                    radius="default"
                    border
                >
                    <Stack gap="default">
                        <Stack gap="tight">
                            <Heading level={3} variant="group">
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
                            <Heading level={3} variant="group">
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
                                    <RiskBadge
                                        safe={security.requireTwoFactorForAdmin}
                                        label={
                                            security.requireTwoFactorForAdmin
                                                ? t('admin.security.enforced')
                                                : t('admin.security.optional')
                                        }
                                    />
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
                                    <RiskBadge
                                        safe={security.httpsEnabled}
                                        label={
                                            security.httpsEnabled
                                                ? t(
                                                      'admin.security.httpsActive',
                                                  )
                                                : t(
                                                      'admin.security.httpsInactive',
                                                  )
                                        }
                                    />
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

                <Stack gap="tight">
                    <Heading level={3} variant="group">
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
                                        <Heading level={4} variant="group">
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
                                        {item.key === 'content' && (
                                            <Link
                                                href={pagesIndex()}
                                                tone="primary"
                                            >
                                                {t('admin.pages.navLabel')}
                                            </Link>
                                        )}
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

AdminSystem.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
        {
            title: 'admin.platformBadge',
            href: systemIndex(),
        },
    ],
};
