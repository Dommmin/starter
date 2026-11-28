import { Alert, Link, Section } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

type ContentPreviewBarProps = {
    preview: App.Data.Content.ContentPreviewData;
};

/**
 * Banner of a content preview: whether the shown (last saved) version is
 * visible to visitors and, in the panel, the way back to its editor. Rendered only when the server sends `preview`.
 */
export function ContentPreviewBar({ preview }: ContentPreviewBarProps) {
    const { t, formatDate } = useTranslation();

    const title =
        preview.state === 'published'
            ? t('preview.titlePublished')
            : t('preview.titleUnpublished');

    const state =
        preview.state === 'scheduled'
            ? preview.publishAt
                ? t('preview.state.scheduled', {
                      date: formatDate(preview.publishAt, {
                          dateStyle: 'long',
                          timeStyle: 'short',
                      }),
                  })
                : t('preview.state.scheduledUndated')
            : preview.state === 'draft'
              ? t('preview.state.draft')
              : t('preview.state.published');

    return (
        <Section spacing="compact" tone="subtle" container="reading">
            <Alert
                title={title}
                description={
                    <>
                        {state} {t('preview.savedVersion')}{' '}
                        {preview.editUrl && (
                            <Link href={preview.editUrl} tone="primary">
                                {t('preview.backToEdit')}
                            </Link>
                        )}
                    </>
                }
            />
        </Section>
    );
}
