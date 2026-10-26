import {
    AppearancePreview,
    Badge,
    Button,
    Grid,
    Heading,
    Inline,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

type AccentColor = App.Enums.AccentColor;
type TypographyPreset = App.Enums.TypographyPreset;

export type SiteSkin = {
    key: 'studio' | 'office' | 'nature';
    accent: AccentColor;
    typography: TypographyPreset;
};

/** Ready-made pairs of an accent and a typography preset. */
export const SITE_SKINS: SiteSkin[] = [
    { key: 'studio', accent: 'default', typography: 'default' },
    { key: 'office', accent: 'blue', typography: 'classic' },
    { key: 'nature', accent: 'green', typography: 'default' },
];

type SiteSkinsProps = {
    /** Current form values; '' means the configured fallback. */
    accent: string;
    typography: string;
    onApply: (skin: SiteSkin) => void;
    disabled?: boolean;
};

/**
 * Gallery of site skins with a sample of each one. Applying a skin only
 * fills the accent and typography fields; the form saves it.
 */
export function SiteSkins({
    accent,
    typography,
    onApply,
    disabled = false,
}: SiteSkinsProps) {
    const { t } = useTranslation();

    return (
        <Surface>
            <Stack gap="default">
                <Stack gap="tight">
                    <Heading level={2} variant="group">
                        {t('admin.siteSettings.skins.title')}
                    </Heading>
                    <Text variant="caption" tone="muted">
                        {t('admin.siteSettings.skins.description')}
                    </Text>
                </Stack>
                <Grid layout="cards" gap="default">
                    {SITE_SKINS.map((skin) => {
                        const name = t(
                            `admin.siteSettings.skins.${skin.key}.name`,
                        );
                        const isCurrent =
                            accent === skin.accent &&
                            (typography || 'default') === skin.typography;

                        return (
                            <Stack key={skin.key} gap="tight">
                                <AppearancePreview
                                    accent={skin.accent}
                                    typography={skin.typography}
                                    label={t(
                                        'admin.siteSettings.skins.sample',
                                        {
                                            name,
                                        },
                                    )}
                                >
                                    <Stack gap="tight">
                                        <Text variant="label" tone="primary">
                                            {t(
                                                'admin.siteSettings.skins.sampleEyebrow',
                                            )}
                                        </Text>
                                        <Heading level={3} variant="section">
                                            {t(
                                                'admin.siteSettings.skins.sampleTitle',
                                            )}
                                        </Heading>
                                        <Inline gap="tight">
                                            <Button variant="primary">
                                                {t(
                                                    'admin.siteSettings.skins.sampleAction',
                                                )}
                                            </Button>
                                        </Inline>
                                    </Stack>
                                </AppearancePreview>
                                <Inline gap="tight" justify="between">
                                    <Heading level={3} variant="group">
                                        {name}
                                    </Heading>
                                    {isCurrent ? (
                                        <Badge tone="success">
                                            {t(
                                                'admin.siteSettings.skins.current',
                                            )}
                                        </Badge>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={disabled}
                                            onClick={() => onApply(skin)}
                                            ariaLabel={t(
                                                'admin.siteSettings.skins.applyNamed',
                                                { name },
                                            )}
                                        >
                                            {t(
                                                'admin.siteSettings.skins.apply',
                                            )}
                                        </Button>
                                    )}
                                </Inline>
                                <Text variant="caption" tone="muted">
                                    {t(
                                        `admin.siteSettings.skins.${skin.key}.description`,
                                    )}
                                </Text>
                            </Stack>
                        );
                    })}
                </Grid>
            </Stack>
        </Surface>
    );
}
