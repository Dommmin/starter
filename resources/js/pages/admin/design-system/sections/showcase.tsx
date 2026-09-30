import type { ComponentType, ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Grid,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';

/**
 * One component family of the showcase (a table of the component registry,
 * e.g. ADM-01). `id` is the page anchor (`/admin/design-system#adm-01`);
 * the title and description are translation keys.
 */
export type ShowcaseFamily = {
    id: string;
    titleKey: string;
    descriptionKey: string;
    Component: ComponentType;
};

/**
 * State vocabulary of the registry's `verified` definition, plus the
 * API-specific axes (variants, sizes, link targets) a component shows.
 * Labels live in `admin.designSystem.states.*`.
 */
export type ShowcaseStateKey =
    | 'default'
    | 'variants'
    | 'tones'
    | 'sizes'
    | 'disabled'
    | 'pending'
    | 'loading'
    | 'empty'
    | 'error'
    | 'success'
    | 'longContent'
    | 'noMedia'
    | 'brokenMedia'
    | 'withMedia'
    | 'internalLink'
    | 'external'
    | 'download'
    | 'anchor'
    | 'responsiveLabel'
    | 'withTooltip'
    | 'decorative'
    | 'meaningful'
    | 'horizontal'
    | 'vertical'
    | 'sides'
    | 'dismissible'
    | 'indeterminate'
    | 'withDescription'
    | 'withAction'
    | 'minimal'
    | 'withIcon'
    | 'withValue'
    | 'placeholder'
    | 'required'
    | 'readonly'
    | 'formError'
    | 'focusFirstError';

export type ShowcaseComponentProps = {
    /** Public API name of the component, e.g. `Button` (not translated). */
    name: string;
    /**
     * `compact` fits four states per row on desktop (buttons, badges);
     * `wide` gives two per row for full-width demos (alerts, progress).
     */
    layout?: 'compact' | 'wide';
    /** States that do not apply to this component, listed explicitly. */
    notApplicable?: ShowcaseStateKey[];
    children: ReactNode;
};

/** A component card: its name, a grid of state demos and the N/A list. */
export function ShowcaseComponent({
    name,
    layout = 'compact',
    notApplicable = [],
    children,
}: ShowcaseComponentProps) {
    const { t } = useTranslation();

    return (
        <Card padding="compact">
            <CardHeader>
                <CardTitle>{name}</CardTitle>
            </CardHeader>
            <CardContent>
                <Stack gap="default">
                    <Grid
                        layout={layout === 'compact' ? 'features' : 'split'}
                        gap="tight"
                    >
                        {children}
                    </Grid>
                    {notApplicable.length > 0 && (
                        <Text variant="caption" tone="muted">
                            {t('admin.designSystem.notApplicable', {
                                states: notApplicable
                                    .map((state) =>
                                        t(`admin.designSystem.states.${state}`),
                                    )
                                    .join(', '),
                            })}
                        </Text>
                    )}
                </Stack>
            </CardContent>
        </Card>
    );
}

export type ShowcaseStateProps = {
    state: ShowcaseStateKey;
    /** Optional translated detail shown after the state label. */
    detail?: string;
    /** Stretch the demo to the cell width (progress, skeleton, alert). */
    fill?: boolean;
    children: ReactNode;
};

/** One labelled cell of the component × state grid. */
export function ShowcaseState({
    state,
    detail,
    fill = false,
    children,
}: ShowcaseStateProps) {
    const { t } = useTranslation();
    const label = t(`admin.designSystem.states.${state}`);

    return (
        <Stack gap="tight" align={fill ? 'stretch' : 'start'}>
            <Text variant="caption" tone="muted" as="span">
                {detail ? `${label} · ${detail}` : label}
            </Text>
            {children}
        </Stack>
    );
}
