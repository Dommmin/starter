import { usePage } from '@inertiajs/react';
import { History } from 'lucide-react';
import {
    Heading,
    Icon,
    Inline,
    ItemList,
    Link,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as auditIndex } from '@/routes/admin/audit';

type HistoryProps = {
    recordHistory?: App.Data.Admin.Audit.RecordHistoryData;
};

/**
 * Change timeline of the edited record: who did what and which fields
 * changed (never the values), newest first. Rendered only when the server
 * shares `recordHistory`, i.e. for users who may read the audit log.
 */
export function RecordHistory() {
    const { recordHistory } = usePage<HistoryProps>().props;
    const { t, formatDate } = useTranslation();

    if (!recordHistory) {
        return null;
    }

    return (
        <Surface as="section" padding="default" border>
            <Stack gap="default">
                <Inline gap="tight" align="center">
                    <Icon icon={History} tone="muted" />
                    <Heading level={2} variant="group">
                        {t('admin.history.title')}
                    </Heading>
                </Inline>
                {recordHistory.entries.length === 0 ? (
                    <Text variant="caption" tone="muted">
                        {t('admin.history.empty')}
                    </Text>
                ) : (
                    <ItemList
                        items={recordHistory.entries.map((entry) => ({
                            id: String(entry.id),
                            content: (
                                <Stack gap="none">
                                    <Text>
                                        {t('admin.history.entry', {
                                            actor:
                                                entry.actorName ??
                                                t('admin.audit.system'),
                                            action: t(
                                                `admin.audit.actions.${entry.action}`,
                                            ),
                                        })}
                                    </Text>
                                    {entry.changedFields.length > 0 && (
                                        <Text variant="caption" tone="muted">
                                            {t('admin.history.fields', {
                                                fields: entry.changedFields.join(
                                                    ', ',
                                                ),
                                            })}
                                        </Text>
                                    )}
                                </Stack>
                            ),
                            end: entry.createdAt
                                ? formatDate(entry.createdAt, {
                                      dateStyle: 'medium',
                                      timeStyle: 'short',
                                  })
                                : '—',
                        }))}
                    />
                )}
                <Text variant="caption" tone="muted">
                    {t('admin.history.limit', { limit: recordHistory.limit })}
                </Text>
                <Link href={auditIndex()} tone="muted">
                    {t('admin.history.all')}
                </Link>
            </Stack>
        </Surface>
    );
}
