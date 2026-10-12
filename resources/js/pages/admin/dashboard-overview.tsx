import type { RouteDefinition } from '@/wayfinder';
import { AlertTriangle, CheckCircle2, FileText, History } from 'lucide-react';
import {
    BarChart,
    Badge,
    Button,
    DistributionBar,
    EmptyState,
    Grid,
    Heading,
    Icon,
    Inline,
    ItemList,
    Link,
    PageHeader,
    Stack,
    StatTile,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    create as articlesCreate,
    index as articlesIndex,
} from '@/routes/admin/articles';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as contactIndex } from '@/routes/admin/contact';
import { index as mediaIndex } from '@/routes/admin/media';
import {
    create as pagesCreate,
    index as pagesIndex,
} from '@/routes/admin/pages';
import { index as systemIndex } from '@/routes/admin/system';
import { index as usersIndex } from '@/routes/admin/users';

type Overview = App.Data.Admin.Dashboard.DashboardOverviewData;

type AttentionRow = {
    id: string;
    /** Number of affected items; absent for a yes/no platform risk. */
    count?: number;
    text: string;
    href: RouteDefinition<'get'>;
    severity: 'danger' | 'warning';
};

type ActivityEntry = App.Data.Admin.Audit.AuditLogListItemData;

export type SystemWarnings = {
    debugOutsideLocal: boolean;
    twoFactorOptional: boolean;
} | null;

export type DashboardOverviewProps = {
    overview: Overview;
    systemWarnings: SystemWarnings;
};

/** Consecutive identical entries (same actor, action and subject) collapse into one row. */
function groupActivity(
    entries: ActivityEntry[],
): { entry: ActivityEntry; count: number }[] {
    const groups: { entry: ActivityEntry; count: number }[] = [];

    for (const entry of entries) {
        const last = groups.at(-1);

        if (
            last &&
            last.entry.actorName === entry.actorName &&
            last.entry.action === entry.action &&
            last.entry.subjectType === entry.subjectType &&
            last.entry.subjectId === entry.subjectId
        ) {
            last.count++;
            continue;
        }

        groups.push({ entry, count: 1 });
    }

    return groups;
}

/**
 * Panel dashboard, ordered by what a person should do: things needing
 * attention, key figures with their trend, the inbox trend, the state of
 * the content and the latest changes. Blocks the user may not open are
 * absent from the payload and are not rendered.
 */
export function DashboardOverview({
    overview,
    systemWarnings,
}: DashboardOverviewProps) {
    const { t, formatDate, formatNumber } = useTranslation();
    const {
        siteName,
        contentLocale,
        articles,
        pages,
        contact,
        quarantinedMedia,
        recentActivity,
        publishing,
        attention,
    } = overview;

    const total = (counts: typeof articles) =>
        counts ? counts.published + counts.drafts + counts.scheduled : 0;
    const hasContent = total(articles) + total(pages) > 0;
    const showEmptyState = !hasContent && (articles !== null || pages !== null);

    const attentionRows: AttentionRow[] = [];
    const pushRow = (
        id: string,
        count: number | null | undefined,
        text: string,
        href: RouteDefinition<'get'>,
        severity: AttentionRow['severity'],
    ) => {
        if (count) {
            attentionRows.push({ id, count, text, href, severity });
        }
    };

    if (attention) {
        pushRow(
            'failedMessages',
            attention.failedMessages,
            t('admin.overview.attention.failedMessages'),
            contactIndex({ query: { status: 'failed' } }),
            'danger',
        );
        pushRow(
            'rejectedMedia',
            attention.rejectedMedia,
            t('admin.overview.attention.rejectedMedia'),
            mediaIndex({ query: { status: 'rejected' } }),
            'danger',
        );
        pushRow(
            'adminsWithoutTwoFactor',
            attention.adminsWithoutTwoFactor,
            t('admin.overview.attention.adminsWithoutTwoFactor'),
            usersIndex(),
            'warning',
        );
        pushRow(
            'quarantinedMedia',
            attention.quarantinedMedia,
            t('admin.overview.attention.quarantinedMedia'),
            mediaIndex({ query: { status: 'quarantine' } }),
            'warning',
        );
        pushRow(
            'scheduledSoon',
            attention.scheduledSoon,
            t('admin.overview.attention.scheduledSoon', {
                hours: attention.soonHours,
            }),
            articlesIndex({
                query: { status: 'scheduled', locale: contentLocale },
            }),
            'warning',
        );
        pushRow(
            'staleDrafts',
            attention.staleDrafts,
            t('admin.overview.attention.staleDrafts', {
                days: attention.staleDays,
            }),
            articlesIndex({
                query: { status: 'draft', locale: contentLocale },
            }),
            'warning',
        );
    }

    if (systemWarnings?.debugOutsideLocal) {
        attentionRows.unshift({
            id: 'debugOutsideLocal',
            text: t('admin.overview.attention.debugOutsideLocal'),
            href: systemIndex(),
            severity: 'danger',
        });
    }

    if (systemWarnings?.twoFactorOptional) {
        attentionRows.push({
            id: 'twoFactorOptional',
            text: t('admin.overview.attention.twoFactorOptional'),
            href: systemIndex(),
            severity: 'warning',
        });
    }

    const daily = contact?.daily ?? [];
    const dailyTotals = daily.map((day) => day.delivered + day.undelivered);
    const messagesTotal = dailyTotals.reduce((sum, value) => sum + value, 0);
    const peakIndex = dailyTotals.indexOf(Math.max(0, ...dailyTotals));
    const dayLabel = (date: string, style: 'short' | 'long') =>
        formatDate(
            `${date}T12:00:00`,
            style === 'short'
                ? { day: 'numeric', month: 'numeric' }
                : { dateStyle: 'long' },
        );
    const mediaToReview =
        attention && quarantinedMedia !== null
            ? (attention.quarantinedMedia ?? 0) + (attention.rejectedMedia ?? 0)
            : null;

    const actionLabel = (action: App.Enums.AuditAction) =>
        t(`admin.audit.actions.${action}`);
    const subjectLabel = (entry: ActivityEntry) => {
        const type = t(`admin.audit.subjects.${entry.subjectType}`, {
            defaultValue: entry.subjectType,
        });

        return entry.subjectId === null
            ? type
            : t('admin.audit.subjectLabel', { type, id: entry.subjectId });
    };

    return (
        <Stack gap="relaxed">
            <PageHeader
                density="compact"
                title={siteName}
                description={t('admin.overview.description')}
                actions={
                    <>
                        {articles !== null && (
                            <Button href={articlesCreate()} size="sm">
                                {t('admin.overview.newArticle')}
                            </Button>
                        )}
                        {pages !== null && (
                            <Button
                                href={pagesCreate()}
                                size="sm"
                                variant="outline"
                            >
                                {t('admin.overview.newPage')}
                            </Button>
                        )}
                    </>
                }
            />

            {showEmptyState && (
                <Surface tone="default" padding="default" border>
                    <EmptyState
                        icon={FileText}
                        title={t('admin.overview.emptyTitle')}
                        description={t('admin.overview.emptyDescription')}
                        action={
                            pages !== null ? (
                                <Button href={pagesCreate()}>
                                    {t('admin.overview.emptyAction')}
                                </Button>
                            ) : articles !== null ? (
                                <Button href={articlesCreate()}>
                                    {t('admin.overview.emptyArticleAction')}
                                </Button>
                            ) : undefined
                        }
                    />
                </Surface>
            )}

            <Grid layout="stats" gap="tight">
                {contact && (
                    <StatTile
                        label={t('admin.overview.kpi.messages', {
                            days: contact.recentDays,
                        })}
                        value={formatNumber(contact.recent)}
                        delta={{
                            value: contact.recent - contact.previous,
                            label: t('admin.overview.kpi.vsPrevious', {
                                days: contact.recentDays,
                            }),
                        }}
                        href={contactIndex()}
                        linkLabel={t('admin.overview.kpi.messagesLink', {
                            days: contact.recentDays,
                            total: contact.recent,
                        })}
                    />
                )}
                {publishing && (
                    <StatTile
                        label={t('admin.overview.kpi.published', {
                            days: publishing.days,
                        })}
                        value={formatNumber(publishing.recent)}
                        delta={{
                            value: publishing.recent - publishing.previous,
                            label: t('admin.overview.kpi.vsPrevious', {
                                days: publishing.days,
                            }),
                        }}
                        href={articlesIndex({
                            query: {
                                status: 'published',
                                locale: contentLocale,
                            },
                        })}
                        linkLabel={t('admin.overview.kpi.publishedLink', {
                            total: publishing.recent,
                        })}
                    />
                )}
                {articles && publishing && (
                    <StatTile
                        label={t('admin.overview.kpi.scheduled')}
                        value={formatNumber(articles.scheduled)}
                        hint={
                            publishing.nextScheduledAt
                                ? t('admin.overview.kpi.nextOn', {
                                      date: formatDate(
                                          publishing.nextScheduledAt,
                                          {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          },
                                      ),
                                  })
                                : t('admin.overview.kpi.noneScheduled')
                        }
                        href={articlesIndex({
                            query: {
                                status: 'scheduled',
                                locale: contentLocale,
                            },
                        })}
                        linkLabel={t('admin.overview.kpi.scheduledLink', {
                            total: articles.scheduled,
                        })}
                    />
                )}
                {mediaToReview !== null && attention && (
                    <StatTile
                        label={t('admin.overview.kpi.media')}
                        value={formatNumber(mediaToReview)}
                        hint={t('admin.overview.kpi.mediaHint', {
                            quarantine: attention.quarantinedMedia ?? 0,
                            rejected: attention.rejectedMedia ?? 0,
                        })}
                        href={mediaIndex()}
                        linkLabel={t('admin.overview.kpi.mediaLink', {
                            total: mediaToReview,
                        })}
                    />
                )}
            </Grid>

            <Grid layout="split-asymmetric" gap="default">
                <Surface as="section" padding="default" border>
                    <Stack gap="default">
                        <Heading level={2} variant="group">
                            {t('admin.overview.attention.title')}
                        </Heading>
                        {attentionRows.length === 0 ? (
                            <Inline gap="tight" align="center">
                                <Icon icon={CheckCircle2} tone="success" />
                                <Text tone="muted">
                                    {t('admin.overview.attention.allGood')}
                                </Text>
                            </Inline>
                        ) : (
                            <ItemList
                                items={attentionRows.map((row) => ({
                                    id: row.id,
                                    lead:
                                        row.count === undefined ? (
                                            <Icon
                                                icon={AlertTriangle}
                                                tone={
                                                    row.severity === 'danger'
                                                        ? 'danger'
                                                        : 'primary'
                                                }
                                            />
                                        ) : (
                                            <Badge
                                                tone={
                                                    row.severity === 'danger'
                                                        ? 'danger'
                                                        : 'primary'
                                                }
                                            >
                                                {formatNumber(row.count)}
                                            </Badge>
                                        ),
                                    content: row.text,
                                    end: (
                                        <Button
                                            href={row.href}
                                            size="sm"
                                            variant="ghost"
                                            ariaLabel={`${t('admin.overview.attention.review')}: ${row.text}${row.count === undefined ? '' : ` (${row.count})`}`}
                                        >
                                            {t(
                                                'admin.overview.attention.review',
                                            )}
                                        </Button>
                                    ),
                                }))}
                            />
                        )}
                    </Stack>
                </Surface>

                {contact && (
                    <Surface as="section" padding="default" border>
                        <BarChart
                            title={t('admin.overview.chart.title')}
                            summary={
                                messagesTotal > 0 && daily[peakIndex]
                                    ? t('admin.overview.chart.summary', {
                                          days: contact.chartDays,
                                          total: messagesTotal,
                                          date: dayLabel(
                                              daily[peakIndex].date,
                                              'long',
                                          ),
                                          peak: dailyTotals[peakIndex],
                                      })
                                    : t('admin.overview.chart.empty', {
                                          days: contact.chartDays,
                                      })
                            }
                            series={[
                                {
                                    id: 'delivered',
                                    label: t('admin.overview.chart.delivered'),
                                    tone: 'chart-1',
                                },
                                {
                                    id: 'undelivered',
                                    label: t(
                                        'admin.overview.chart.undelivered',
                                    ),
                                    tone: 'chart-2',
                                    hatched: true,
                                },
                            ]}
                            data={daily.map((day) => ({
                                key: day.date,
                                label: dayLabel(day.date, 'short'),
                                fullLabel: dayLabel(day.date, 'long'),
                                values: {
                                    delivered: day.delivered,
                                    undelivered: day.undelivered,
                                },
                            }))}
                            labels={{
                                showTable: t('admin.overview.chart.showTable'),
                                hideTable: t('admin.overview.chart.hideTable'),
                                category: t('admin.overview.chart.day'),
                                total: t('admin.overview.chart.total'),
                            }}
                            empty={t('admin.overview.chart.empty', {
                                days: contact.chartDays,
                            })}
                            compactCount={14}
                            formatValue={(value) => formatNumber(value)}
                        />
                    </Surface>
                )}
            </Grid>

            <Grid layout="split" gap="default">
                {(articles || pages) && (
                    <Surface as="section" padding="default" border>
                        <Stack gap="default">
                            <Heading level={2} variant="group">
                                {t('admin.overview.pipeline.title')}
                            </Heading>
                            {articles && (
                                <DistributionBar
                                    label={t('admin.overview.articles.title')}
                                    emptyLabel={t(
                                        'admin.overview.pipeline.empty',
                                    )}
                                    formatValue={(value) => formatNumber(value)}
                                    segments={[
                                        {
                                            id: 'published',
                                            label: t(
                                                'admin.overview.published',
                                            ),
                                            value: articles.published,
                                            tone: 'chart-1',
                                        },
                                        {
                                            id: 'scheduled',
                                            label: t(
                                                'admin.overview.scheduled',
                                            ),
                                            value: articles.scheduled,
                                            tone: 'chart-2',
                                        },
                                        {
                                            id: 'drafts',
                                            label: t('admin.overview.drafts'),
                                            value: articles.drafts,
                                            tone: 'neutral',
                                        },
                                    ]}
                                />
                            )}
                            {pages && (
                                <DistributionBar
                                    label={t('admin.overview.pages.title')}
                                    emptyLabel={t(
                                        'admin.overview.pipeline.empty',
                                    )}
                                    formatValue={(value) => formatNumber(value)}
                                    segments={[
                                        {
                                            id: 'published',
                                            label: t(
                                                'admin.overview.published',
                                            ),
                                            value: pages.published,
                                            tone: 'chart-1',
                                        },
                                        {
                                            id: 'scheduled',
                                            label: t(
                                                'admin.overview.scheduled',
                                            ),
                                            value: pages.scheduled,
                                            tone: 'chart-2',
                                        },
                                        {
                                            id: 'drafts',
                                            label: t('admin.overview.drafts'),
                                            value: pages.drafts,
                                            tone: 'neutral',
                                        },
                                    ]}
                                />
                            )}
                            <Inline gap="default" wrap>
                                {articles && (
                                    <Link href={articlesIndex()} tone="muted">
                                        {t('admin.overview.articles.all')}
                                    </Link>
                                )}
                                {pages && (
                                    <Link href={pagesIndex()} tone="muted">
                                        {t('admin.overview.pages.all')}
                                    </Link>
                                )}
                            </Inline>
                        </Stack>
                    </Surface>
                )}

                {recentActivity !== null && (
                    <Surface as="section" padding="default" border>
                        <Stack gap="default">
                            <Inline gap="tight" align="center">
                                <Icon icon={History} tone="muted" />
                                <Heading level={2} variant="group">
                                    {t('admin.overview.activity.title')}
                                </Heading>
                            </Inline>
                            {recentActivity.length === 0 ? (
                                <Text variant="caption" tone="muted">
                                    {t('admin.overview.activity.empty')}
                                </Text>
                            ) : (
                                <ItemList
                                    items={groupActivity(recentActivity).map(
                                        ({ entry, count }) => ({
                                            id: String(entry.id),
                                            content: (
                                                <>
                                                    {t(
                                                        'admin.overview.activity.entry',
                                                        {
                                                            actor:
                                                                entry.actorName ??
                                                                t(
                                                                    'admin.audit.system',
                                                                ),
                                                            action: actionLabel(
                                                                entry.action,
                                                            ),
                                                            subject:
                                                                subjectLabel(
                                                                    entry,
                                                                ),
                                                        },
                                                    )}
                                                    {count > 1 && (
                                                        <Badge tone="neutral">
                                                            {t(
                                                                'admin.overview.repeated',
                                                                { count },
                                                            )}
                                                        </Badge>
                                                    )}
                                                </>
                                            ),
                                            end: entry.createdAt
                                                ? formatDate(entry.createdAt, {
                                                      dateStyle: 'medium',
                                                      timeStyle: 'short',
                                                  })
                                                : '—',
                                        }),
                                    )}
                                />
                            )}
                            <Link href={auditIndex()} tone="muted">
                                {t('admin.overview.activity.all')}
                            </Link>
                        </Stack>
                    </Surface>
                )}
            </Grid>
        </Stack>
    );
}
