import type { RouteDefinition } from '@/wayfinder';
import type { LucideIcon } from 'lucide-react';
import { FileText, History, Images, Mail, Newspaper } from 'lucide-react';
import {
    Button,
    DescriptionList,
    EmptyState,
    Grid,
    Heading,
    Icon,
    Link,
    PageHeader,
    Stack,
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

type Overview = App.Data.Admin.Dashboard.DashboardOverviewData;

type CountRow = {
    id: string;
    label: string;
    count: number;
    /** Full, translated link name, e.g. "Draft articles: 3". */
    linkLabel: string;
    href: RouteDefinition<'get'>;
};

type CountCardProps = {
    title: string;
    icon: LucideIcon;
    rows: CountRow[];
    allHref: RouteDefinition<'get'>;
    allLabel: string;
};

function CountCard({ title, icon, rows, allHref, allLabel }: CountCardProps) {
    const { formatNumber } = useTranslation();

    return (
        <Surface as="section" tone="default" padding="default" border>
            <Stack gap="default">
                <Stack gap="tight">
                    <Icon icon={icon} size="default" tone="primary" />
                    <Heading level={2} variant="group">
                        {title}
                    </Heading>
                </Stack>
                <DescriptionList
                    items={rows.map((row) => ({
                        id: row.id,
                        label: row.label,
                        value: (
                            <Link
                                href={row.href}
                                tone="primary"
                                ariaLabel={row.linkLabel}
                            >
                                {formatNumber(row.count)}
                            </Link>
                        ),
                    }))}
                />
                <Link href={allHref} tone="muted">
                    {allLabel}
                </Link>
            </Stack>
        </Surface>
    );
}

export type DashboardOverviewProps = {
    overview: Overview;
};

/**
 * Content-first top of the panel dashboard: the site name, counts linking to
 * the filtered lists and the newest audit entries. Blocks the user may not
 * open are absent from the payload and are not rendered.
 */
export function DashboardOverview({ overview }: DashboardOverviewProps) {
    const { t, formatDate } = useTranslation();
    const {
        siteName,
        contentLocale,
        articles,
        pages,
        contact,
        quarantinedMedia,
        recentActivity,
    } = overview;

    const total = (counts: typeof articles) =>
        counts ? counts.published + counts.drafts + counts.scheduled : 0;
    const hasContent = total(articles) + total(pages) > 0;
    const showEmptyState = !hasContent && (articles !== null || pages !== null);

    const cards: CountCardProps[] = [];

    if (articles && hasContent) {
        cards.push({
            title: t('admin.overview.articles.title'),
            icon: Newspaper,
            allHref: articlesIndex(),
            allLabel: t('admin.overview.articles.all'),
            rows: [
                {
                    id: 'published',
                    label: t('admin.overview.published'),
                    count: articles.published,
                    linkLabel: t('admin.overview.articles.published', {
                        total: articles.published,
                    }),
                    href: articlesIndex({
                        query: { status: 'published', locale: contentLocale },
                    }),
                },
                {
                    id: 'drafts',
                    label: t('admin.overview.drafts'),
                    count: articles.drafts,
                    linkLabel: t('admin.overview.articles.drafts', {
                        total: articles.drafts,
                    }),
                    href: articlesIndex({
                        query: { status: 'draft', locale: contentLocale },
                    }),
                },
                {
                    id: 'scheduled',
                    label: t('admin.overview.scheduled'),
                    count: articles.scheduled,
                    linkLabel: t('admin.overview.articles.scheduled', {
                        total: articles.scheduled,
                    }),
                    href: articlesIndex({
                        query: { status: 'scheduled', locale: contentLocale },
                    }),
                },
            ],
        });
    }

    if (pages && hasContent) {
        cards.push({
            title: t('admin.overview.pages.title'),
            icon: FileText,
            allHref: pagesIndex(),
            allLabel: t('admin.overview.pages.all'),
            rows: [
                {
                    id: 'published',
                    label: t('admin.overview.published'),
                    count: pages.published + pages.scheduled,
                    linkLabel: t('admin.overview.pages.published', {
                        total: pages.published + pages.scheduled,
                    }),
                    href: pagesIndex({
                        query: { status: 'published', locale: contentLocale },
                    }),
                },
                {
                    id: 'drafts',
                    label: t('admin.overview.drafts'),
                    count: pages.drafts,
                    linkLabel: t('admin.overview.pages.drafts', {
                        total: pages.drafts,
                    }),
                    href: pagesIndex({
                        query: { status: 'draft', locale: contentLocale },
                    }),
                },
            ],
        });
    }

    if (contact) {
        cards.push({
            title: t('admin.overview.contact.title'),
            icon: Mail,
            allHref: contactIndex(),
            allLabel: t('admin.overview.contact.all'),
            rows: [
                {
                    id: 'recent',
                    label: t('admin.overview.contact.recent', {
                        days: contact.recentDays,
                    }),
                    count: contact.recent,
                    linkLabel: t('admin.overview.contact.recentLink', {
                        days: contact.recentDays,
                        total: contact.recent,
                    }),
                    href: contactIndex(),
                },
                {
                    id: 'failed',
                    label: t('admin.overview.contact.failed'),
                    count: contact.failed,
                    linkLabel: t('admin.overview.contact.failedLink', {
                        total: contact.failed,
                    }),
                    href: contactIndex({ query: { status: 'failed' } }),
                },
            ],
        });
    }

    if (quarantinedMedia !== null) {
        cards.push({
            title: t('admin.overview.media.title'),
            icon: Images,
            allHref: mediaIndex(),
            allLabel: t('admin.overview.media.all'),
            rows: [
                {
                    id: 'quarantine',
                    label: t('admin.overview.media.quarantine'),
                    count: quarantinedMedia,
                    linkLabel: t('admin.overview.media.quarantineLink', {
                        total: quarantinedMedia,
                    }),
                    href: mediaIndex({ query: { status: 'quarantine' } }),
                },
            ],
        });
    }

    const actionLabel = (action: App.Enums.AuditAction) =>
        t(`admin.audit.actions.${action}`);
    const subjectLabel = (entry: App.Data.Admin.Audit.AuditLogListItemData) => {
        const type = t(`admin.audit.subjects.${entry.subjectType}`, {
            defaultValue: entry.subjectType,
        });

        return entry.subjectId === null
            ? type
            : t('admin.audit.subjectLabel', { type, id: entry.subjectId });
    };

    return (
        <Stack gap="default">
            <PageHeader
                title={siteName}
                description={t('admin.overview.description')}
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

            {cards.length > 0 && (
                <Grid layout="split" gap="tight">
                    {cards.map((card) => (
                        <CountCard key={card.title} {...card} />
                    ))}
                </Grid>
            )}

            {recentActivity !== null && (
                <Surface as="section" tone="default" padding="default" border>
                    <Stack gap="default">
                        <Stack gap="tight">
                            <Icon
                                icon={History}
                                size="default"
                                tone="primary"
                            />
                            <Heading level={2} variant="group">
                                {t('admin.overview.activity.title')}
                            </Heading>
                        </Stack>
                        {recentActivity.length === 0 ? (
                            <Text variant="caption" tone="muted">
                                {t('admin.overview.activity.empty')}
                            </Text>
                        ) : (
                            <DescriptionList
                                items={recentActivity.map((entry) => ({
                                    id: String(entry.id),
                                    label: entry.createdAt
                                        ? formatDate(entry.createdAt, {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          })
                                        : '—',
                                    value: t('admin.overview.activity.entry', {
                                        actor:
                                            entry.actorName ??
                                            t('admin.audit.system'),
                                        action: actionLabel(entry.action),
                                        subject: subjectLabel(entry),
                                    }),
                                }))}
                            />
                        )}
                        <Link href={auditIndex()} tone="muted">
                            {t('admin.overview.activity.all')}
                        </Link>
                    </Stack>
                </Surface>
            )}
        </Stack>
    );
}
