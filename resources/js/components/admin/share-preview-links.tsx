import { router, usePage } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import { useState } from 'react';
import {
    Button,
    Heading,
    Icon,
    Inline,
    ItemList,
    notify,
    SelectField,
    Stack,
    Surface,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

type PreviewLinksProps = {
    previewLinks?: App.Data.Admin.PreviewLinks.PreviewLinkListItemData[];
};

const LIFETIMES = [1, 7, 30];

/**
 * Create and revoke preview links that work without an account. The new
 * address is shown only once. Rendered only when the server shares
 * `previewLinks`, i.e. for users who may change the record.
 */
export function SharePreviewLinks({
    locales,
    createUrl,
    revokeUrl,
}: {
    /** Languages with a saved translation. */
    locales: string[];
    createUrl: string;
    revokeUrl: (link: number) => string;
}) {
    const { previewLinks } = usePage<PreviewLinksProps>().props;
    const { t, formatDate } = useTranslation();
    const [locale, setLocale] = useState(locales[0] ?? '');
    const [days, setDays] = useState('7');
    const [createdUrl, setCreatedUrl] = useState<string | null>(null);
    const [isCreating, setIsCreating] = useState(false);

    if (!previewLinks) {
        return null;
    }

    const selected = locales.includes(locale) ? locale : (locales[0] ?? '');

    const create = () => {
        router.post(
            createUrl,
            { locale: selected, days: Number(days) },
            {
                preserveScroll: true,
                onStart: () => setIsCreating(true),
                onFinish: () => setIsCreating(false),
                onSuccess: (page) => {
                    const flash = page.flash as
                        | { sharedPreviewUrl?: string }
                        | undefined;
                    setCreatedUrl(flash?.sharedPreviewUrl ?? null);
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0];

                    if (message) {
                        notify({ tone: 'danger', message });
                    }
                },
            },
        );
    };

    const copy = (url: string) => {
        void navigator.clipboard.writeText(url).then(() =>
            notify({
                tone: 'success',
                message: t('admin.previewLinks.copied'),
            }),
        );
    };

    return (
        <Surface as="section" padding="default" border>
            <Stack gap="default">
                <Inline gap="tight" align="center">
                    <Icon icon={Link2} tone="muted" />
                    <Heading level={2} variant="group">
                        {t('admin.previewLinks.title')}
                    </Heading>
                </Inline>
                <Text variant="caption" tone="muted">
                    {t('admin.previewLinks.description')}
                </Text>
                {locales.length === 0 ? (
                    <Text>{t('admin.previewLinks.needSaved')}</Text>
                ) : (
                    <Inline gap="default" align="end">
                        <SelectField
                            name="previewLocale"
                            label={t('admin.previewLinks.language')}
                            value={selected}
                            onChange={setLocale}
                            options={locales.map((code) => ({
                                value: code,
                                label: code.toUpperCase(),
                            }))}
                        />
                        <SelectField
                            name="previewDays"
                            label={t('admin.previewLinks.lifetime')}
                            value={days}
                            onChange={setDays}
                            options={LIFETIMES.map((count) => ({
                                value: String(count),
                                label: t('admin.previewLinks.days', { count }),
                            }))}
                        />
                        <Button isPending={isCreating} onClick={create}>
                            {t('admin.previewLinks.create')}
                        </Button>
                    </Inline>
                )}
                {createdUrl && (
                    <Stack gap="tight">
                        <TextField
                            name="previewUrl"
                            label={t('admin.previewLinks.url')}
                            description={t('admin.previewLinks.once')}
                            value={createdUrl}
                            onChange={() => undefined}
                        />
                        <Inline gap="tight">
                            <Button
                                variant="outline"
                                onClick={() => copy(createdUrl)}
                            >
                                {t('admin.previewLinks.copy')}
                            </Button>
                        </Inline>
                    </Stack>
                )}
                <Heading level={3} variant="group">
                    {t('admin.previewLinks.active')}
                </Heading>
                {previewLinks.length === 0 ? (
                    <Text variant="caption" tone="muted">
                        {t('admin.previewLinks.empty')}
                    </Text>
                ) : (
                    <ItemList
                        items={previewLinks.map((link) => ({
                            id: String(link.id),
                            content: (
                                <Text>
                                    {link.locale.toUpperCase()} ·{' '}
                                    {t('admin.previewLinks.entry', {
                                        date: formatDate(link.expiresAt, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        }),
                                        author:
                                            link.authorName ??
                                            t('admin.audit.system'),
                                    })}
                                </Text>
                            ),
                            end: (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(revokeUrl(link.id), {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                setCreatedUrl(null),
                                        })
                                    }
                                >
                                    {t('admin.previewLinks.revoke')}
                                </Button>
                            ),
                        }))}
                    />
                )}
            </Stack>
        </Surface>
    );
}
