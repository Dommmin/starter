import { Rocket, ShieldCheck, Sparkles, Zap } from 'lucide-react';
import { useState } from 'react';
import { HomeSections } from '@/components/home/home-sections';
import {
    Button,
    CTA,
    FeatureGrid,
    Hero,
    Image,
    Stack,
    type FeatureGridItem,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import {
    ShowcaseComponent,
    ShowcaseState,
    type ShowcaseFamily,
} from '../../admin/design-system/sections/showcase';
import { demoImage, demoMediaImage } from './demo-data';

type HomeSection = App.Data.Home.HomeSectionData;

/** WEB-02 — landing: hero, feature grid, call to action, home sections. */
function LandingSection() {
    const { t } = useTranslation();
    const demo = (key: string) => t(`admin.designSystem.web.landing.${key}`);
    const longName = t('admin.designSystem.web.longName');
    const longText = t('admin.designSystem.web.longText');
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
                          },
                          {
                              title: longName,
                              url: '/articles?page=2',
                              excerpt: longText,
                              publishedAt: null,
                              cover: null,
                              coverAlt: '',
                          },
                      ],
            },
        },
    ];

    return (
        <Stack gap="default">
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
                        contactForm={{ token: '' }}
                        fallbackTitle={demo('homeFallbackTitle')}
                    />
                </ShowcaseState>
            </ShowcaseComponent>
        </Stack>
    );
}

export const landingFamily: ShowcaseFamily = {
    id: 'web-02',
    titleKey: 'admin.designSystem.web.landing.title',
    descriptionKey: 'admin.designSystem.web.landing.description',
    Component: LandingSection,
};
