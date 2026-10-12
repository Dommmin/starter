import { Check } from 'lucide-react';
import { ContactForm } from '@/components/contact-form';
import {
    Accordion,
    Avatar,
    Badge,
    Button,
    CTA,
    EmptyState,
    Grid,
    Heading,
    Hero,
    Icon,
    Image,
    Inline,
    isBentoWide,
    Link,
    NumberedList,
    ProjectTile,
    Reveal,
    Section,
    Separator,
    Stack,
    StatList,
    type StatListItem,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { homeIcons } from './home-icons';

type HomeSection = App.Data.Home.HomeSectionData;
type HomeLink = App.Data.Home.HomeLinkData;

type HomeSectionsProps = {
    sections: HomeSection[];
    /** Page-level contact form state (one form per page). */
    contactForm: App.Data.Contact.ContactFormData;
    /**
     * Page `h1` used only when no hero section is rendered (the hero title
     * is otherwise the single `h1`), e.g. the site name.
     */
    fallbackTitle: string;
};

/**
 * Enabled home page sections in the order sent by the server. Each section
 * carries its fixed anchor (`App.Enums.HomeSectionAnchor`) as `id`, the
 * contract navigation links rely on, on a wrapping element: the hero's
 * plain wrapper, every other section's `Reveal`, which fades it in when it
 * is scrolled into view.
 */
export function HomeSections({
    sections,
    contactForm,
    fallbackTitle,
}: HomeSectionsProps) {
    const hasHero = sections.some((section) => section.type === 'hero');
    const context = pageContext(sections);

    return (
        <>
            {!hasHero && (
                <Section spacing="compact" container="wide">
                    <Heading level={1} variant="page" align="center">
                        {fallbackTitle}
                    </Heading>
                </Section>
            )}
            {sections.map((section, index) =>
                section.type === 'hero' ? (
                    <div key={section.id} id={section.anchor}>
                        <HeroSection content={section.content} />
                    </div>
                ) : (
                    <Reveal key={section.id} id={section.anchor}>
                        <HomeSectionContent
                            section={section}
                            contactForm={contactForm}
                            context={context}
                            tone={bandTone(index, hasHero)}
                        />
                    </Reveal>
                ),
            )}
        </>
    );
}

type BandTone = 'default' | 'subtle';

/**
 * Background of the section at `index`: bands alternate from the order
 * on the page, not the section type, so disabling or reordering sections
 * never puts two bands of the same tone next to each other. The first band
 * after the hero is subtle.
 */
function bandTone(index: number, hasHero: boolean): BandTone {
    return (index - (hasHero ? 1 : 0)) % 2 === 0 ? 'subtle' : 'default';
}

/** What sections may borrow from the rest of the page. */
type PageContext = {
    /** Anchor of the pricing section, when the page shows one. */
    pricingAnchor: string | null;
};

function pageContext(sections: HomeSection[]): PageContext {
    const pricing = sections.find((section) => section.type === 'pricing');

    return { pricingAnchor: pricing?.anchor ?? null };
}

function toStatItems(stats: App.Data.Home.HeroStatData[]): StatListItem[] {
    return stats.map((stat, index) => ({
        id: `${index}-${stat.label}`,
        value: stat.value,
        label: stat.label,
    }));
}

function HomeSectionContent({
    section,
    contactForm,
    context,
    tone,
}: {
    section: Exclude<HomeSection, { type: 'hero' }>;
    contactForm: App.Data.Contact.ContactFormData;
    context: PageContext;
    tone: BandTone;
}) {
    const { t } = useTranslation();

    switch (section.type) {
        case 'features':
            return (
                <FeaturesSection
                    tone={tone}
                    content={section.content}
                    pricingAnchor={context.pricingAnchor}
                />
            );
        case 'faq':
            return <FaqSection tone={tone} content={section.content} />;
        case 'testimonials':
            return (
                <TestimonialsSection tone={tone} content={section.content} />
            );
        case 'latest_articles':
            return (
                <LatestArticlesSection tone={tone} content={section.content} />
            );
        case 'contact':
            return (
                <ContactForm
                    contactForm={contactForm}
                    eyebrow={t('home.eyebrows.contact')}
                    title={section.content.title}
                    description={section.content.description}
                    aside={<ContactSteps />}
                    tone={tone}
                />
            );
        case 'cta':
            return <CtaSection content={section.content} />;
        case 'portfolio':
            return <PortfolioSection tone={tone} content={section.content} />;
        case 'team':
            return <TeamSection tone={tone} content={section.content} />;
        case 'pricing':
            return <PricingSection tone={tone} content={section.content} />;
        default:
            return assertNever(section);
    }
}

/** How the first contact goes, beside the contact form. */
function ContactSteps() {
    const { t } = useTranslation();

    return (
        <Stack gap="default">
            <Heading level={3} variant="group">
                {t('home.contactStepsTitle')}
            </Heading>
            <NumberedList
                size="compact"
                items={[1, 2, 3].map((step) => ({
                    id: String(step),
                    description: t(`home.contactStep${step}`),
                }))}
            />
        </Stack>
    );
}

function assertNever(value: never): never {
    throw new Error(`Unsupported home section: ${JSON.stringify(value)}`);
}

function Actions({
    primary,
    secondary,
    secondaryVariant = 'outline',
}: {
    primary: HomeLink | null;
    secondary: HomeLink | null;
    /**
     * `outline` takes the page colours, so on an inverted surface (CTA) it
     * would disappear; `secondary` keeps its own fill there.
     */
    secondaryVariant?: 'outline' | 'secondary';
}) {
    if (!primary && !secondary) {
        return null;
    }

    // A lone secondary action (e.g. the primary one was dropped for a
    // signed-in viewer) becomes the main action of the section.
    const main = primary ?? secondary;
    const extra = primary ? secondary : null;

    return (
        <>
            {main && (
                <Button variant="primary" size="lg" href={main.url}>
                    {main.label}
                </Button>
            )}
            {extra && (
                <Button variant={secondaryVariant} size="lg" href={extra.url}>
                    {extra.label}
                </Button>
            )}
        </>
    );
}

/**
 * Hero copy with the key figures counting up under the actions and an
 * optional DAM image beside it (the LCP image, so it loads eagerly) in a
 * raised frame with depth.
 */
function HeroSection({ content }: { content: App.Data.Home.HomeHeroData }) {
    const { image } = content;
    const stats = toStatItems(content.stats);

    return (
        <Hero
            eyebrow={content.eyebrow ?? undefined}
            title={content.title}
            description={content.description ?? undefined}
            actions={
                content.primaryAction || content.secondaryAction ? (
                    <Actions
                        primary={content.primaryAction}
                        secondary={content.secondaryAction}
                    />
                ) : undefined
            }
            proof={
                stats.length > 0 ? (
                    <Stack gap="relaxed">
                        <Separator />
                        <StatList animate align="start" items={stats} />
                    </Stack>
                ) : undefined
            }
            media={
                image ? (
                    <Surface tone="raised" padding="none" radius="lg">
                        <Image
                            src={image.src}
                            srcset={image.srcset}
                            sources={image.sources}
                            width={image.width}
                            height={image.height}
                            alt={content.imageAlt}
                            sizes="(min-width: 64rem) 36rem, 100vw"
                            priority
                        />
                    </Surface>
                ) : undefined
            }
            mediaDepth
            align={image ? 'start' : 'center'}
        />
    );
}

/**
 * Services as a numbered list beside a heading that stays in view while
 * the list scrolls past it on large screens, closed by a way on to pricing.
 */
function FeaturesSection({
    content,
    tone,
    pricingAnchor,
}: {
    content: App.Data.Home.FeaturesContentData;
    tone: BandTone;
    pricingAnchor: string | null;
}) {
    const { t } = useTranslation();
    const hasLead = Boolean(content.title || content.description);

    return (
        <Section tone={tone} spacing="relaxed" container="wide">
            <Grid
                layout={hasLead ? 'split-asymmetric' : 'single'}
                gap="relaxed"
            >
                {hasLead && (
                    <SectionHeading
                        eyebrow={t('home.eyebrows.features')}
                        title={content.title}
                        description={content.description}
                    />
                )}
                <Stack gap="relaxed">
                    <NumberedList
                        headingLevel={content.title ? 3 : 2}
                        items={content.items.map((item, index) => ({
                            id: `${index}-${item.title}`,
                            title: item.title,
                            description: item.description,
                            trailing: item.icon ? (
                                <Icon
                                    icon={homeIcons[item.icon]}
                                    size="lg"
                                    tone="primary"
                                />
                            ) : undefined,
                        }))}
                    />
                    {pricingAnchor && (
                        <Inline>
                            <Button
                                variant="outline"
                                href={`#${pricingAnchor}`}
                            >
                                {t('home.seePricing')}
                            </Button>
                        </Inline>
                    )}
                </Stack>
            </Grid>
        </Section>
    );
}

/**
 * Start-aligned section heading. `split` puts the description beside the
 * title on wide screens (editorial header above full-width content);
 * otherwise it sits below (e.g. a sticky lead column).
 */
function SectionHeading({
    eyebrow,
    title,
    description,
    split = false,
    align = 'start',
}: {
    /** Short label above the title, e.g. "What we do". */
    eyebrow?: string;
    title: string | null;
    description?: string | null;
    split?: boolean;
    align?: 'start' | 'center';
}) {
    if (!title && !description) {
        return null;
    }

    const heading = title && (
        <Stack gap="tight" align={align === 'center' ? 'center' : 'start'}>
            {eyebrow && (
                <Text variant="label" tone="primary" align={align}>
                    {eyebrow}
                </Text>
            )}
            <Heading level={2} variant="page" align={align}>
                {title}
            </Heading>
        </Stack>
    );
    const lead = description && (
        <Text variant="lead" tone="muted" align={align}>
            {description}
        </Text>
    );

    if (split && heading && lead) {
        return (
            <Grid layout="split" gap="default">
                {heading}
                {lead}
            </Grid>
        );
    }

    return (
        <Stack gap="tight" align={align === 'center' ? 'center' : 'start'}>
            {heading}
            {lead}
        </Stack>
    );
}

function FaqSection({
    content,
    tone,
}: {
    content: App.Data.Home.HomeFaqData;
    tone: BandTone;
}) {
    const { t } = useTranslation();

    return (
        <Section tone={tone} spacing="default" container="reading">
            <Stack gap="relaxed">
                <SectionHeading
                    title={content.title}
                    description={content.description}
                />
                {content.items.length === 0 ? (
                    <EmptyState title={t('home.faqEmpty')} />
                ) : (
                    <Accordion
                        type="multiple"
                        items={content.items.map((item) => ({
                            id: String(item.id),
                            trigger: item.question,
                            content: <Text tone="muted">{item.answer}</Text>,
                        }))}
                    />
                )}
            </Stack>
        </Section>
    );
}

/**
 * Client quotes beside a sticky heading backed by proof figures:
 * the first quote is featured in large type, the rest follow quieter.
 */
function TestimonialsSection({
    content,
    tone,
}: {
    content: App.Data.Home.TestimonialsContentData;
    tone: BandTone;
}) {
    const { t } = useTranslation();
    const proof = toStatItems(content.stats);

    return (
        <Section tone={tone} spacing="relaxed" container="wide">
            <Grid
                layout={content.title ? 'split-asymmetric' : 'single'}
                gap="relaxed"
            >
                {content.title && (
                    <Stack gap="relaxed">
                        <SectionHeading
                            eyebrow={t('home.eyebrows.testimonials')}
                            title={content.title}
                        />
                        {proof.length > 0 && (
                            <StatList align="start" items={proof} />
                        )}
                    </Stack>
                )}
                <Stack gap="default">
                    {content.items.map((item, index) => (
                        <Surface
                            key={`${index}-${item.author}`}
                            as="article"
                            tone={index === 0 ? 'raised' : 'default'}
                            padding={index === 0 ? 'relaxed' : 'default'}
                            radius="lg"
                        >
                            <Stack gap="default">
                                <Text
                                    as="blockquote"
                                    variant={index === 0 ? 'lead' : 'body'}
                                >
                                    {item.quote}
                                </Text>
                                <Inline gap="default" align="center">
                                    <Avatar
                                        name={item.author}
                                        fallback={initials(item.author)}
                                    />
                                    <Stack gap="none">
                                        <Text variant="label" as="p">
                                            {item.author}
                                        </Text>
                                        {item.role && (
                                            <Text variant="caption" as="p">
                                                {item.role}
                                            </Text>
                                        )}
                                    </Stack>
                                </Inline>
                            </Stack>
                        </Surface>
                    ))}
                </Stack>
            </Grid>
        </Section>
    );
}

/** Up to two initials of a name, e.g. "Anna Nowak" → "AN". */
function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Latest articles as an editorial list beside a sticky heading with the
 * link to all articles: reads well with one article as with many.
 */
function LatestArticlesSection({
    content,
    tone,
}: {
    content: App.Data.Home.HomeLatestArticlesData;
    tone: BandTone;
}) {
    const { t, formatDate } = useTranslation();

    return (
        <Section tone={tone} spacing="relaxed" container="wide">
            <Grid layout="split-asymmetric" gap="relaxed">
                <Stack gap="relaxed">
                    <SectionHeading
                        eyebrow={t('home.eyebrows.latest_articles')}
                        title={content.title ?? t('home.latestArticlesTitle')}
                    />
                    <Inline>
                        <Button variant="outline" href={content.listUrl}>
                            {t('home.allArticles')}
                        </Button>
                    </Inline>
                </Stack>
                {content.items.length === 0 ? (
                    <EmptyState title={t('home.latestArticlesEmpty')} />
                ) : (
                    <Stack as="ul" gap="default">
                        {content.items.map((article, index) => (
                            <li key={article.url}>
                                <Stack gap="default">
                                    {index > 0 && <Separator />}
                                    <Stack gap="tight">
                                        {article.publishedAt && (
                                            <Text
                                                variant="label"
                                                tone="primary"
                                                as="p"
                                            >
                                                {formatDate(
                                                    article.publishedAt,
                                                    { dateStyle: 'long' },
                                                )}
                                            </Text>
                                        )}
                                        <Heading level={3} variant="subsection">
                                            <Link
                                                href={article.url}
                                                ariaLabel={t('home.readMore', {
                                                    title: article.title,
                                                })}
                                            >
                                                {article.title}
                                            </Link>
                                        </Heading>
                                        {article.excerpt && (
                                            <Text tone="muted">
                                                {article.excerpt}
                                            </Text>
                                        )}
                                    </Stack>
                                </Stack>
                            </li>
                        ))}
                    </Stack>
                )}
            </Grid>
        </Section>
    );
}

/**
 * Projects as a zig-zag bento grid: wide tiles show the mock-up beside the
 * copy, every tile lifts with an accent glow under the pointer.
 */
function PortfolioSection({
    content,
    tone,
}: {
    content: App.Data.Home.HomePortfolioData;
    tone: BandTone;
}) {
    const { t } = useTranslation();

    return (
        <Section tone={tone} spacing="relaxed" container="wide">
            <Stack gap="relaxed">
                <SectionHeading
                    eyebrow={t('home.eyebrows.portfolio')}
                    title={content.title}
                    description={content.description}
                    split
                />
                <Grid layout="bento" gap="default">
                    {content.items.map((item, index) => {
                        const wide = isBentoWide(index);

                        return (
                            <ProjectTile
                                key={`${index}-${item.title}`}
                                wide={wide}
                                eyebrow={item.category ?? undefined}
                                headingLevel={content.title ? 3 : 2}
                                description={item.description ?? undefined}
                                media={
                                    item.image ? (
                                        <Image
                                            src={item.image.src}
                                            srcset={item.image.srcset}
                                            sources={item.image.sources}
                                            width={item.image.width}
                                            height={item.image.height}
                                            alt={item.imageAlt}
                                            sizes={
                                                wide
                                                    ? '(min-width: 64rem) 26rem, (min-width: 48rem) 45vw, 100vw'
                                                    : '(min-width: 64rem) 20rem, (min-width: 48rem) 45vw, 100vw'
                                            }
                                            fit="cover"
                                        />
                                    ) : undefined
                                }
                                title={
                                    item.url ? (
                                        <Link
                                            href={item.url}
                                            external
                                            ariaLabel={t('home.viewProject', {
                                                title: item.title,
                                            })}
                                        >
                                            {item.title}
                                        </Link>
                                    ) : (
                                        item.title
                                    )
                                }
                            />
                        );
                    })}
                </Grid>
            </Stack>
        </Section>
    );
}

function TeamSection({
    content,
    tone,
}: {
    content: App.Data.Home.HomeTeamData;
    tone: BandTone;
}) {
    const { t } = useTranslation();

    return (
        <Section tone={tone} spacing="relaxed" container="wide">
            <Grid layout={content.title ? 'features' : 'cards'} gap="default">
                {(content.title || content.description) && (
                    <SectionHeading
                        eyebrow={t('home.eyebrows.team')}
                        title={content.title}
                        description={content.description}
                    />
                )}
                {content.items.map((member, index) => (
                    <Surface
                        key={`${index}-${member.name}`}
                        as="article"
                        radius="lg"
                        spotlight
                    >
                        <Stack gap="default">
                            {member.photo ? (
                                <Image
                                    src={member.photo.src}
                                    srcset={member.photo.srcset}
                                    sources={member.photo.sources}
                                    width={member.photo.width}
                                    height={member.photo.height}
                                    alt={member.photoAlt}
                                    sizes="(min-width: 64rem) 22rem, (min-width: 40rem) 45vw, 100vw"
                                    frame="square"
                                />
                            ) : (
                                <Avatar
                                    name={member.name}
                                    fallback={initials(member.name)}
                                    size="lg"
                                />
                            )}
                            <Stack gap="none">
                                <Heading
                                    level={content.title ? 3 : 2}
                                    variant="group"
                                >
                                    {member.name}
                                </Heading>
                                <Text variant="label" tone="primary">
                                    {member.role}
                                </Text>
                            </Stack>
                            {member.bio && (
                                <Text tone="muted">{member.bio}</Text>
                            )}
                            {member.skills.length > 0 && (
                                <Inline gap="tight" wrap>
                                    {member.skills.map((skill, skillIndex) => (
                                        <Badge
                                            key={`${skillIndex}-${skill}`}
                                            tone="outline"
                                        >
                                            {skill}
                                        </Badge>
                                    ))}
                                </Inline>
                            )}
                        </Stack>
                    </Surface>
                ))}
            </Grid>
        </Section>
    );
}

function PricingSection({
    content,
    tone,
}: {
    content: App.Data.Home.HomePricingData;
    tone: BandTone;
}) {
    const { t, formatNumber } = useTranslation();

    function price(plan: App.Data.Home.HomePricingPlanData): string {
        if (plan.priceMinor === null) {
            return t('home.priceOnRequest');
        }

        const amount = formatNumber(plan.priceMinor / 100, {
            style: 'currency',
            currency: plan.currency,
            minimumFractionDigits: plan.priceMinor % 100 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        });

        return plan.priceFrom ? t('home.priceFrom', { price: amount }) : amount;
    }

    return (
        <Section tone={tone} spacing="relaxed" container="wide">
            <Stack gap="relaxed">
                <SectionHeading
                    eyebrow={t('home.eyebrows.pricing')}
                    title={content.title}
                    description={content.description}
                    split
                />
                <Grid layout="cards">
                    {content.items.map((plan, index) => {
                        const tone = plan.highlighted ? 'inverted' : 'default';
                        const textTone = plan.highlighted
                            ? 'inverted'
                            : 'muted';

                        return (
                            <Surface
                                key={`${index}-${plan.name}`}
                                as="article"
                                tone={tone}
                                radius="lg"
                            >
                                <Stack gap="default">
                                    <Inline
                                        justify="between"
                                        align="center"
                                        wrap
                                    >
                                        <Heading
                                            level={content.title ? 3 : 2}
                                            variant="group"
                                            tone={
                                                plan.highlighted
                                                    ? 'inverted'
                                                    : 'default'
                                            }
                                        >
                                            {plan.name}
                                        </Heading>
                                        {plan.highlighted && (
                                            <Badge tone="neutral">
                                                {t('home.recommended')}
                                            </Badge>
                                        )}
                                    </Inline>
                                    <Stack gap="none">
                                        <Text
                                            variant={
                                                plan.priceMinor === null
                                                    ? 'lead'
                                                    : 'figure'
                                            }
                                            as="p"
                                            tone={
                                                plan.highlighted
                                                    ? 'inverted'
                                                    : 'default'
                                            }
                                        >
                                            {price(plan)}
                                        </Text>
                                        {plan.period && (
                                            <Text
                                                variant="caption"
                                                as="p"
                                                tone={textTone}
                                            >
                                                {plan.period}
                                            </Text>
                                        )}
                                    </Stack>
                                    {plan.description && (
                                        <Text tone={textTone}>
                                            {plan.description}
                                        </Text>
                                    )}
                                    {plan.features.length > 0 && (
                                        <Stack as="ul" gap="tight">
                                            {plan.features.map(
                                                (feature, featureIndex) => (
                                                    <li
                                                        key={`${featureIndex}-${feature}`}
                                                    >
                                                        <Inline
                                                            gap="tight"
                                                            align="center"
                                                        >
                                                            <Icon
                                                                icon={Check}
                                                                size="sm"
                                                                tone="primary"
                                                            />
                                                            <Text
                                                                tone={
                                                                    plan.highlighted
                                                                        ? 'inverted'
                                                                        : 'default'
                                                                }
                                                            >
                                                                {feature}
                                                            </Text>
                                                        </Inline>
                                                    </li>
                                                ),
                                            )}
                                        </Stack>
                                    )}
                                    {plan.action && (
                                        <Button
                                            variant={
                                                plan.highlighted
                                                    ? 'primary'
                                                    : 'outline'
                                            }
                                            href={plan.action.url}
                                        >
                                            {plan.action.label}
                                        </Button>
                                    )}
                                </Stack>
                            </Surface>
                        );
                    })}
                </Grid>
                {content.note && (
                    <Text variant="caption" tone="muted">
                        {content.note}
                    </Text>
                )}
            </Stack>
        </Section>
    );
}

function CtaSection({ content }: { content: App.Data.Home.HomeCtaData }) {
    return (
        <CTA
            title={content.title}
            description={content.description ?? undefined}
            tone="inverted"
            actions={
                <Actions
                    primary={content.primaryAction}
                    secondary={content.secondaryAction}
                    secondaryVariant="secondary"
                />
            }
        />
    );
}
