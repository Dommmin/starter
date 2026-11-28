import { router, usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    Button,
    ConfirmDialog,
    Heading,
    Icon,
    Inline,
    ItemList,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

type RevisionsProps = {
    revisions?: App.Data.Admin.Revisions.RevisionListItemData[];
};

const KEPT_REVISIONS = 20;

/**
 * Earlier versions of the edited page or article, newest first, with a
 * confirmed restore. Rendered only when the server shares `revisions`,
 * i.e. for users who may change the record.
 */
export function ContentRevisions({
    restoreUrl,
}: {
    restoreUrl: (revision: number) => string;
}) {
    const { revisions } = usePage<RevisionsProps>().props;
    const { t, formatDate } = useTranslation();
    const [selected, setSelected] = useState<number | null>(null);
    const [isRestoring, setIsRestoring] = useState(false);

    if (!revisions) {
        return null;
    }

    const restore = () => {
        if (selected === null) {
            return;
        }

        router.post(
            restoreUrl(selected),
            {},
            {
                // The form must reload with the restored content.
                preserveState: false,
                onStart: () => setIsRestoring(true),
                onFinish: () => {
                    setIsRestoring(false);
                    setSelected(null);
                },
            },
        );
    };

    return (
        <Surface as="section" padding="default" border>
            <Stack gap="default">
                <Inline gap="tight" align="center">
                    <Icon icon={RotateCcw} tone="muted" />
                    <Heading level={2} variant="group">
                        {t('admin.revisions.title')}
                    </Heading>
                </Inline>
                {revisions.length === 0 ? (
                    <Text variant="caption" tone="muted">
                        {t('admin.revisions.empty')}
                    </Text>
                ) : (
                    <ItemList
                        items={revisions.map((revision) => ({
                            id: String(revision.id),
                            content: (
                                <Stack gap="none">
                                    <Text>
                                        {formatDate(revision.createdAt, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        })}
                                    </Text>
                                    <Text variant="caption" tone="muted">
                                        {t('admin.revisions.entry', {
                                            author:
                                                revision.authorName ??
                                                t('admin.audit.system'),
                                        })}
                                    </Text>
                                    <Text variant="caption" tone="muted">
                                        {revision.titles.join(' · ')}
                                    </Text>
                                </Stack>
                            ),
                            end: (
                                <Button
                                    variant="outline"
                                    onClick={() => setSelected(revision.id)}
                                >
                                    {t('admin.revisions.restore')}
                                </Button>
                            ),
                        }))}
                    />
                )}
                <Text variant="caption" tone="muted">
                    {t('admin.revisions.limit', { limit: KEPT_REVISIONS })}
                </Text>
            </Stack>
            <ConfirmDialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
                title={t('admin.revisions.confirmTitle')}
                description={t('admin.revisions.confirmDescription')}
                confirmLabel={t('admin.revisions.confirm')}
                cancelLabel={t('admin.trash.cancel')}
                closeLabel={t('admin.trash.cancel')}
                onConfirm={restore}
                isPending={isRestoring}
            />
        </Surface>
    );
}
