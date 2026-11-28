import { router } from '@inertiajs/react';
import {
    Button,
    Heading,
    Inline,
    ItemList,
    notify,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { destroy, insert } from '@/routes/admin/pages/blocks';

/**
 * Shared blocks of the shown language: add a linked copy to the page or
 * delete a block that no section uses.
 */
export function SharedBlockList({
    pageId,
    blocks,
    canUpdate,
}: {
    pageId: number;
    blocks: App.Data.Admin.Pages.ContentBlockData[];
    canUpdate: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Stack gap="default">
            <Heading level={2} variant="group">
                {t('admin.pages.blocks.title')}
            </Heading>
            <ItemList
                items={blocks.map((block) => ({
                    id: String(block.id),
                    content: (
                        <Stack gap="none">
                            <Text>{block.name}</Text>
                            <Text variant="caption" tone="muted">
                                {t(`admin.homeSections.types.${block.type}`)}
                                {' · '}
                                {t('admin.pages.blocks.uses', {
                                    count: block.uses,
                                })}
                            </Text>
                        </Stack>
                    ),
                    end: canUpdate ? (
                        <Inline gap="tight">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        insert.url(pageId),
                                        { content_block_id: block.id },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('admin.pages.blocks.insert')}
                            </Button>
                            {block.uses === 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(
                                            destroy.url({
                                                page: pageId,
                                                contentBlock: block.id,
                                            }),
                                            {
                                                preserveScroll: true,
                                                onError: (errors) =>
                                                    notify({
                                                        tone: 'danger',
                                                        message:
                                                            errors.block ?? '',
                                                    }),
                                            },
                                        )
                                    }
                                >
                                    {t('admin.pages.blocks.remove')}
                                </Button>
                            )}
                        </Inline>
                    ) : undefined,
                }))}
            />
        </Stack>
    );
}
