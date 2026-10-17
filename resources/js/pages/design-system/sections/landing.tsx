import { Rocket, ShieldCheck, Sparkles, Zap } from 'lucide-react';
import { useState } from 'react';
import { HomeSections } from '@/components/home/home-sections';
import {
    AppearancePreview,
    Button,
    CTA,
    FeatureGrid,
    Heading,
    Hero,
    Image,
    Inline,
    Stack,
    StatList,
    Text,
    TypographyPreview,
    type FeatureGridItem,
    type StatListItem,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from '../../admin/design-system/sections/showcase';
import { demoImage, demoMediaImage } from './demo-data';

type HomeSection = App.Data.Home.HomeSectionData;

/** WEB-02 — landing: hero, key figures, feature grid, call to action, home sections. */
function LandingSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`showcase.web.landing.${key}`);
    const longName = t('showcase.web.longName');
    const longText = t('showcase.web.longText');
    const [isHomeEmpty, setHomeEmpty] = useState(false);

    const actions = (
        <>
            <Button variant="primary" size="lg" href="#web-05">
                {demo('primaryAction')}
            </Button>
            <Button variant="outline" size="lg" href="#web-04">
                {demo('secondaryAction')}
            </Button>
        </>
    );

    const features: FeatureGridItem[] = [
        {
            id: 'fast',
            icon: Zap,
            title: demo('featureFast'),
            description: demo('featureFastText'),
        },
        {
            id: 'safe',
            icon: ShieldCheck,
            title: demo('featureSafe'),
            description: demo('featureSafeText'),
        },
        {
            id: 'ready',
            icon: Rocket,
            title: demo('featureReady'),
            description: demo('featureReadyText'),
        },
        {
            id: 'polish',
            icon: Sparkles,
            title: longName,
            description: longText,
        },
    ];

    const stats: StatListItem[] = [
        { id: 'projects', value: '120+', label: demo('statProjects') },
        { id: 'clients', value: '45', label: demo('statClients') },
        { id: 'years', value: '12', label: demo('statYears') },
        { id: 'support', value: '24/7', label: demo('statSupport') },
    ];

    const homeSections: HomeSection[] = [
        {
            id: 1,
            anchor: 'features',
            type: 'features',
            content: {
                title: demo('homeFeaturesTitle'),
                description: null,
                items: isHomeEmpty
                    ? []
                    : [
                          {
                              icon: 'zap',
                              title: demo('featureFast'),
                              description: demo('featureFastText'),
                          },
                          {
                              icon: null,
                              title: demo('featureSafe'),
                              description: demo('featureSafeText'),
                          },
                      ],
            },
        },
        {
            id: 2,
            anchor: 'faq',
            type: 'faq',
            content: {
                title: demo('faqTitle'),
                description: null,
                items: isHomeEmpty
                    ? []
                    : [
                          {
                              id: 1,
                              question: demo('faqQuestion'),
                              answer: demo('faqAnswer'),
                          },
                          { id: 2, question: longName, answer: longText },
                      ],
            },
        },
        {
            id: 3,
            anchor: 'testimonials',
            type: 'testimonials',
            content: {
                title: demo('testimonialsTitle'),
                stats: [],
                items: isHomeEmpty
                    ? []
                    : [
                          {
                              quote: demo('testimonialQuote'),
                              author: 'Anna Kowalska',
                              role: demo('testimonialRole'),
                          },
                          {
                              quote: longText,
                              author: 'Łukasz Żółkiewski',
                              role: null,
                          },
                      ],
            },
        },
        {
            id: 4,
            anchor: 'latest-articles',
            type: 'latest_articles',
            content: {
                title: demo('articlesTitle'),
                listUrl: '/articles',
                items: isHomeEmpty
                    ? []
                    : [
                          {
                              title: demo('articleWithCover'),
                              url: '/articles',
                              excerpt: demo('articleExcerpt'),
                              publishedAt: '2026-09-30T08:00:00Z',
                              cover: demoMediaImage,
                              coverAlt: '',
                              tags: [],
                          },
                          {
                              title: longName,
                              url: '/articles?page=2',
                              excerpt: longText,
                              publishedAt: null,
                              cover: null,
                              coverAlt: '',
                              tags: [],
                          },
                      ],
            },
        },
    ];

    return (
        <Stack gap="default">
            <ShowcaseComponent
                name="TypographyPreview · --font-heading"
                layout="wide"
                notApplicable={['loading', 'empty', 'error', 'disabled']}
            >
                {(['default', 'classic'] as const).map((preset) => (
                    <ShowcaseState
                        key={preset}
                        state={preset === 'default' ? 'default' : 'variants'}
                        detail={demo(`typography.${preset}`)}
                    >
                        <TypographyPreview preset={preset}>
                            <Stack gap="tight">
                                <Heading level={3} variant="section">
                                    {demo('typography.heading')}
                                </Heading>
                                <Text variant="figure">128</Text>
                                <Text tone="muted">
                                    {demo('typography.body')}
                                </Text>
                            </Stack>
                        </TypographyPreview>
                    </ShowcaseState>
                ))}
            </ShowcaseComponent>

            <ShowcaseComponent
                name="AppearancePreview · data-accent + data-typography"
                layout="wide"
                notApplicable={['loading', 'empty', 'error', 'disabled']}
            >
                {(
                    [
                        ['blue', 'classic'],
                        ['green', 'default'],
                    ] as const
                ).map(([accent, typography]) => (
                    <ShowcaseState
                        key={accent}
                        state={accent === 'blue' ? 'default' : 'variants'}
                        detail={`accent=${accent} typography=${typography}`}
                    >
                        <AppearancePreview
                            accent={accent}
                            typography={typography}
                            label={demo('typography.heading')}
                        >
                            <Stack gap="tight">
                                <Heading level={3} variant="section">
                                    {demo('typography.heading')}
                                </Heading>
                                <Inline gap="tight">
                                    <Button variant="primary">
                                        {demo('primaryAction')}
                                    </Button>
                                </Inline>
                            </Stack>
                        </AppearancePreview>
                    </ShowcaseState>
                ))}
            </ShowcaseComponent>

            <ShowcaseComponent
                name="Hero"
                layout="full"
                notApplicable={['loading', 'empty', 'error', 'disabled']}
            >
                <ShowcaseState state="default" detail="align=center" fill>
                    <Hero
                        eyebrow={demo('eyebrow')}
                        title={demo('heroTitle')}
                        description={demo('heroDescription')}
                        actions={actions}
                        align="center"
                    />
                </ShowcaseState>
                <ShowcaseState state="withMedia" detail="align=start" fill>
                    <Hero
                        title={demo('heroTitle')}
                        description={demo('heroDescription')}
                        actions={actions}
                        media={
                            <Image
                                src={demoImage.src}
                                width={demoImage.width}
                                height={demoImage.height}
                                alt={demo('heroImageAlt')}
                            />
                        }
                    />
                </ShowcaseState>
                <ShowcaseState state="noMedia" detail={demo('noActions')} fill>
                    <Hero title={demo('heroTitle')} />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <Hero
                        eyebrow={longName}
                        title={longName}
                        description={longText}
                        actions={actions}
                        align="center"
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="StatList"
                layout="full"
                notApplicable={['loading', 'error', 'disabled']}
            >
                <ShowcaseState state="default" detail="align=center · 4" fill>
                    <StatList items={stats} />
                </ShowcaseState>
                <ShowcaseState state="variants" detail="align=start · 3" fill>
                    <StatList items={stats.slice(0, 3)} align="start" />
                </ShowcaseState>
                <ShowcaseState state="variants" detail="align=center · 2" fill>
                    <StatList items={stats.slice(0, 2)} />
                </ShowcaseState>
                <ShowcaseState state="longContent" detail="align=start" fill>
                    <StatList
                        align="start"
                        items={[
                            { id: 'long', value: '1 250+', label: longText },
                            ...stats.slice(1, 3),
                        ]}
                    />
                </ShowcaseState>
                <ShowcaseState state="empty" fill>
                    <StatList items={[]} />
                    <Text variant="caption" tone="muted">
                        {demo('statsEmptyNote')}
                    </Text>
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="FeatureGrid"
                layout="full"
                notApplicable={['loading', 'error', 'disabled']}
            >
                <ShowcaseState state="withIcon" detail="columns=features" fill>
                    <FeatureGrid
                        title={demo('featuresTitle')}
                        description={demo('featuresDescription')}
                        columns="features"
                        items={features}
                    />
                </ShowcaseState>
                <ShowcaseState state="minimal" detail="columns=cards" fill>
                    <FeatureGrid
                        items={features.map(({ icon: _icon, ...item }) => item)}
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="CTA"
                layout="full"
                notApplicable={[
                    'loading',
                    'empty',
                    'error',
                    'disabled',
                    'noMedia',
                ]}
            >
                <ShowcaseState state="default" detail="tone=inverted" fill>
                    <CTA
                        title={demo('ctaTitle')}
                        description={demo('ctaDescription')}
                        actions={
                            <>
                                <Button
                                    variant="primary"
                                    size="lg"
                                    href="#web-05"
                                >
                                    {demo('primaryAction')}
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="lg"
                                    href="#web-04"
                                >
                                    {demo('secondaryAction')}
                                </Button>
                            </>
                        }
                    />
                </ShowcaseState>
                <ShowcaseState state="variants" detail="tone=default" fill>
                    <CTA
                        tone="default"
                        title={demo('ctaTitle')}
                        actions={
                            <Button variant="primary" size="lg" href="#web-05">
                                {demo('primaryAction')}
                            </Button>
                        }
                    />
                </ShowcaseState>
                <ShowcaseState state="longContent" fill>
                    <CTA
                        title={longName}
                        description={longText}
                        actions={
                            <Button variant="primary" size="lg" href="#web-05">
                                {longName}
                            </Button>
                        }
                    />
                </ShowcaseState>
            </ShowcaseComponent>

            <ShowcaseComponent
                name="HomeSections"
                layout="full"
                notApplicable={['loading', 'error', 'disabled']}
            >
                <ShowcaseState
                    state={isHomeEmpty ? 'empty' : 'default'}
                    detail={demo('homeSectionsDetail')}
                    fill
                >
                    <Stack gap="default" align="start">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setHomeEmpty((empty) => !empty)}
                        >
                            {isHomeEmpty
                                ? demo('showFilled')
                                : demo('showEmpty')}
                        </Button>
                    </Stack>
                    <HomeSections
                        sections={homeSections}
                        contactForm={{ token: '', form: null }}
                        fallbackTitle={demo('homeFallbackTitle')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const landingFamily: ShowcaseFamily = {
    id: 'web-02',
    titleKey: 'showcase.web.landing.title',
    descriptionKey: 'showcase.web.landing.description',
    Component: LandingSection,
};
