import { Plus } from 'lucide-react';
import { Fragment, useState } from 'react';
import {
    Accordion,
    AuthHeading,
    Badge,
    Button,
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
    Collapsible,
    Container,
    Fieldset,
    Grid,
    Inline,
    PageHeader,
    Section,
    Separator,
    SplitLayout,
    Stack,
    Surface,
    Tabs,
    Text,
    TextField,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from './showcase';

const stackGaps = ['none', 'tight', 'default', 'relaxed'] as const;
const inlineAligns = ['start', 'center', 'end', 'baseline', 'stretch'] as const;
const gridLayouts = ['single', 'split', 'cards', 'features'] as const;
const containerWidths = ['reading', 'content', 'wide', 'full'] as const;
const sectionTones = ['default', 'subtle', 'raised', 'inverted'] as const;
const surfaceTones = ['default', 'subtle', 'raised', 'inverted'] as const;
const splitRatios = ['even', 'primary-wide', 'primary-narrow'] as const;

/** ADM-02 — layouts and schemas: every layout primitive in its states. */
function LayoutsSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.layouts.${key}`);
    const [fieldValue, setFieldValue] = useState(() => demo('fieldValue'));
    const tile = (label: string) => (
        <Surface tone="subtle" padding="compact" radius="sm" border>
            <Text variant="caption" as="span">
                {label}
            </Text>
        </Surface>
    );

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="PageHeader"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="minimal" fill>
                    <PageHeader title={demo('pageTitle')} />
                </ShowcaseState>
                <ShowcaseState state="withAction" fill>
                    <PageHeader
                        title={demo('pageTitle')}
                        description={demo('pageDescription')}
                        badge={<Badge tone="primary">{demo('badge')}</Badge>}
                        actions={
                            <>
                                <Button size="sm">
                                    <Plus aria-hidden="true" />
                                    {demo('add')}
                                </Button>
                                <Button size="sm" variant="outline">
                                    {demo('export')}
                                </Button>
                            </>
                        }
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <PageHeader
                        title={demo('longTitle')}
                        description={demo('longDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="variants" detail="align=center" fill>
                    <PageHeader
                        title={demo('pageTitle')}
                        description={demo('pageDescription')}
                        align="center"
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="AuthHeading"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState
                    state="default"
                    detail={demo('authHeadingDetail')}
                    fill
                >
                    <AuthHeading
                        title={demo('authHeadingTitle')}
                        description={demo('authHeadingDescription')}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <AuthHeading
                        title={demo('longTitle')}
                        description={demo('longDescription')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Stack · Grid · Container"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="variants" detail="Stack gap" fill>
                    <Grid layout="split" gap="tight">
                        {stackGaps.map((gap) => (
                            <Stack key={gap} gap={gap}>
                                {tile(`gap=${gap}`)}
                                {tile(`gap=${gap}`)}
                            </Stack>
                        ))}
                    </Grid>
                </ShowcaseState>
                <ShowcaseState state="variants" detail="Grid layout" fill>
                    <Stack gap="tight">
                        {gridLayouts.map((layout) => (
                            <Grid key={layout} layout={layout} gap="tight">
                                {tile(layout)}
                                {tile(layout)}
                                {tile(layout)}
                                {tile(layout)}
                            </Grid>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="variants" detail="Container width" fill>
                    <Stack gap="tight">
                        {containerWidths.map((width) => (
                            <Container key={width} width={width} padding="none">
                                {tile(`width=${width}`)}
                            </Container>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Grid layout="features" gap="tight">
                        {tile(demo('longTile'))}
                        {tile(demo('longTile'))}
                        {tile(demo('longTile'))}
                        {tile(demo('longTile'))}
                    </Grid>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Inline"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="variants" detail="Inline gap" fill>
                    <Stack gap="tight">
                        {stackGaps.map((gap) => (
                            <Inline key={gap} gap={gap}>
                                {tile(`gap=${gap}`)}
                                {tile(`gap=${gap}`)}
                            </Inline>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="variants" detail="Inline align" fill>
                    <Stack gap="tight">
                        {inlineAligns.map((align) => (
                            <Inline key={align} align={align}>
                                <Button size="sm" variant="outline">
                                    {`align=${align}`}
                                </Button>
                                <Separator orientation="vertical" />
                                <Badge tone="primary">{demo('badge')}</Badge>
                            </Inline>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="withAction" detail="justify=between" fill>
                    <Surface tone="subtle" padding="compact" radius="sm" border>
                        <Inline justify="between">
                            <Text as="span">{demo('pageTitle')}</Text>
                            <Inline justify="end">
                                <Button size="sm" variant="outline">
                                    {demo('export')}
                                </Button>
                                <Button size="sm">
                                    <Plus aria-hidden="true" />
                                    {demo('add')}
                                </Button>
                            </Inline>
                        </Inline>
                    </Surface>
                </ShowcaseState>
                <ShowcaseState state="longContent" detail="wrap" fill>
                    <Inline gap="tight" wrap>
                        {[
                            ...gridLayouts,
                            ...containerWidths,
                            ...sectionTones,
                        ].map((label) => (
                            <Fragment key={label}>{tile(label)}</Fragment>
                        ))}
                    </Inline>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Section · Surface"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="tones" detail="Section" fill>
                    <Stack gap="tight">
                        {sectionTones.map((tone) => (
                            <Section
                                key={tone}
                                tone={tone}
                                spacing="compact"
                                container="none"
                            >
                                <Container padding="compact">
                                    <Text
                                        variant="label"
                                        as="span"
                                        tone={
                                            tone === 'inverted'
                                                ? 'inverted'
                                                : 'default'
                                        }
                                    >
                                        {`tone=${tone}`}
                                    </Text>
                                </Container>
                            </Section>
                        ))}
                    </Stack>
                </ShowcaseState>
                <ShowcaseState state="tones" detail="Surface" fill>
                    <Grid layout="split" gap="tight">
                        {surfaceTones.map((tone) => (
                            <Surface
                                key={tone}
                                tone={tone}
                                padding="compact"
                                border
                            >
                                <Text
                                    variant="label"
                                    as="span"
                                    tone={
                                        tone === 'inverted'
                                            ? 'inverted'
                                            : 'default'
                                    }
                                >
                                    {`tone=${tone}`}
                                </Text>
                            </Surface>
                        ))}
                    </Grid>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Card"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <Card>
                        <CardHeader>
                            <CardTitle>{demo('cardTitle')}</CardTitle>
                            <CardDescription>
                                {demo('cardDescription')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Text>{demo('cardBody')}</Text>
                        </CardContent>
                    </Card>
                </ShowcaseState>
                <ShowcaseState state="withAction" fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('cardTitle')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Text>{demo('cardBody')}</Text>
                        </CardContent>
                        <CardFooter>
                            <Button size="sm" variant="outline">
                                {demo('open')}
                            </Button>
                        </CardFooter>
                    </Card>
                </ShowcaseState>
                <ShowcaseState state="empty" fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('cardTitle')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Text tone="muted">{demo('cardEmpty')}</Text>
                        </CardContent>
                    </Card>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Card padding="compact">
                        <CardHeader>
                            <CardTitle>{demo('longTitle')}</CardTitle>
                            <CardDescription>
                                {demo('longDescription')}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Fieldset"
                layout="wide"
                notApplicable={[
                    'pending',
                    'loading',
                    'empty',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="withDescription" fill>
                    <Fieldset
                        legend={demo('fieldsetLegend')}
                        description={demo('fieldsetDescription')}
                    >
                        <TextField
                            id="design-system-fieldset-name"
                            name="demo_name"
                            label={demo('fieldLabel')}
                            value={fieldValue}
                            onChange={setFieldValue}
                        />
                    </Fieldset>
                </ShowcaseState>
                <ShowcaseState state="disabled" fill>
                    <Fieldset legend={demo('fieldsetLegend')} disabled>
                        <TextField
                            id="design-system-fieldset-disabled"
                            name="demo_disabled"
                            label={demo('fieldLabel')}
                            value={fieldValue}
                            onChange={setFieldValue}
                        />
                    </Fieldset>
                </ShowcaseState>
                <ShowcaseState state="error" fill>
                    <Fieldset legend={demo('fieldsetLegend')}>
                        <TextField
                            id="design-system-fieldset-error"
                            name="demo_error"
                            label={demo('fieldLabel')}
                            value={fieldValue}
                            onChange={setFieldValue}
                            error={demo('fieldError')}
                        />
                    </Fieldset>
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Fieldset
                        legend={demo('longTitle')}
                        description={demo('longDescription')}
                    >
                        <Text tone="muted">{demo('cardBody')}</Text>
                    </Fieldset>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Collapsible · Accordion"
                layout="wide"
                notApplicable={[
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="collapsed" detail="Collapsible" fill>
                    <Collapsible trigger={demo('collapsibleTrigger')}>
                        <Text tone="muted">{demo('collapsibleBody')}</Text>
                    </Collapsible>
                </ShowcaseState>
                <ShowcaseState state="expanded" detail="Collapsible" fill>
                    <Collapsible
                        trigger={demo('collapsibleTrigger')}
                        defaultOpen
                    >
                        <Text tone="muted">{demo('collapsibleBody')}</Text>
                    </Collapsible>
                </ShowcaseState>
                <ShowcaseState state="disabled" detail="Collapsible" fill>
                    <Collapsible trigger={demo('collapsibleTrigger')} disabled>
                        <Text tone="muted">{demo('collapsibleBody')}</Text>
                    </Collapsible>
                </ShowcaseState>
                <ShowcaseState
                    state="expanded"
                    detail="Accordion type=single"
                    fill
                >
                    <Accordion
                        type="single"
                        defaultOpenIds={['shipping']}
                        items={[
                            {
                                id: 'shipping',
                                trigger: demo('faqShippingQuestion'),
                                content: demo('faqShippingAnswer'),
                            },
                            {
                                id: 'returns',
                                trigger: demo('faqReturnsQuestion'),
                                content: demo('faqReturnsAnswer'),
                            },
                            {
                                id: 'archived',
                                trigger: demo('faqDisabledQuestion'),
                                content: demo('faqReturnsAnswer'),
                                disabled: true,
                            },
                        ]}
                    />
                </ShowcaseState>
                <ShowcaseState
                    state="longContent"
                    detail="Accordion type=multiple"
                    fill
                >
                    <Accordion
                        type="multiple"
                        items={[
                            {
                                id: 'long',
                                trigger: demo('longTitle'),
                                content: demo('longDescription'),
                            },
                            {
                                id: 'returns',
                                trigger: demo('faqReturnsQuestion'),
                                content: demo('faqReturnsAnswer'),
                            },
                        ]}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Tabs"
                layout="wide"
                notApplicable={[
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" fill>
                    <Tabs
                        ariaLabel={demo('tabsLabel')}
                        items={[
                            {
                                value: 'content',
                                label: demo('tabContent'),
                                content: <Text>{demo('tabContentBody')}</Text>,
                            },
                            {
                                value: 'seo',
                                label: demo('tabSeo'),
                                content: <Text>{demo('tabSeoBody')}</Text>,
                            },
                            {
                                value: 'history',
                                label: demo('tabHistory'),
                                content: <Text>{demo('tabHistoryBody')}</Text>,
                                disabled: true,
                            },
                        ]}
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Tabs
                        ariaLabel={demo('tabsLabel')}
                        items={[
                            'pl',
                            'en',
                            'de',
                            'seo',
                            'history',
                            'settings',
                        ].map((value) => ({
                            value,
                            label: demo(`longTabs.${value}`),
                            content: <Text>{demo('tabContentBody')}</Text>,
                        }))}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="SplitLayout"
                layout="wide"
                notApplicable={[
                    'disabled',
                    'pending',
                    'loading',
                    'empty',
                    'error',
                    'success',
                    'noMedia',
                ]}
            >
                {splitRatios.map((ratio) => (
                    <ShowcaseState
                        key={ratio}
                        state="variants"
                        detail={`ratio=${ratio}`}
                        fill
                    >
                        <SplitLayout
                            ratio={ratio}
                            gap="tight"
                            primary={tile(demo('primary'))}
                            secondary={tile(demo('secondary'))}
                        />
                    </ShowcaseState>
                ))}
                <ShowcaseState state="reversedOnMobile" fill>
                    <SplitLayout
                        gap="tight"
                        reverseOnMobile
                        primary={tile(demo('primary'))}
                        secondary={tile(demo('secondary'))}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const layoutsFamily: ShowcaseFamily = {
    id: 'adm-02',
    titleKey: 'admin.designSystem.layouts.title',
    descriptionKey: 'admin.designSystem.layouts.description',
    Component: LayoutsSection,
};
