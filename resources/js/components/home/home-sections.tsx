import { ContactForm } from '@/components/contact-form';
import {
    Accordion,
    Button,
    Card,
    CardContent,
    CTA,
    EmptyState,
    FeatureGrid,
    Grid,
    Heading,
    Hero,
    Image,
    Link,
    Section,
    Stack,
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
 * contract navigation links rely on: on its own `Section` where the page
 * renders one, otherwise on a wrapping element (hero, contact, CTA render
 * their `Section` inside the primitive, which takes no `id`).
 */
export function HomeSections({
    sections,
    contactForm,
    fallbackTitle,
}: HomeSectionsProps) {
    const hasHero = sections.some((section) => section.type === 'hero');

    return (
        <>
            {!hasHero && (
                <Section spacing="compact" container="wide">
                    <Heading level={1} variant="page" align="center">
                        {fallbackTitle}
                    </Heading>
                </Section>
            )}
            {sections.map((section) => (
                <HomeSectionContent
                    key={section.id}
                    section={section}
                    contactForm={contactForm}
                />
            ))}
        </>
    );
}

function HomeSectionContent({
    section,
    contactForm,
}: {
    section: HomeSection;
    contactForm: App.Data.Contact.ContactFormData;
}) {
    const id = section.anchor;

    switch (section.type) {
        case 'hero':
            return (
                <div id={id}>
                    <HeroSection content={section.content} />
                </div>
            );
        case 'features':
            return <FeaturesSection id={id} content={section.content} />;
        case 'faq':
            return <FaqSection id={id} content={section.content} />;
        case 'testimonials':
            return <TestimonialsSection id={id} content={section.content} />;
        case 'latest_articles':
            return <LatestArticlesSection id={id} content={section.content} />;
        case 'contact':
            return (
                <div id={id}>
                    <ContactForm
                        contactForm={contactForm}
                        title={section.content.title}
                        description={section.content.description}
                    />
                </div>
            );
        case 'cta':
            return (
                <div id={id}>
                    <CtaSection content={section.content} />
                </div>
            );
        default:
            return assertNever(section);
    }
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

function HeroSection({ content }: { content: App.Data.Home.HomeHeroData }) {
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
            align="center"
        />
    );
}

function FeaturesSection({
    id,
    content,
}: {
    id: string;
    content: App.Data.Home.FeaturesContentData;
}) {
    return (
        <Section id={id} spacing="relaxed" tone="subtle" container="wide">
            <FeatureGrid
                title={content.title ?? undefined}
                description={content.description ?? undefined}
                columns="features"
                items={content.items.map((item, index) => ({
                    id: `${index}-${item.title}`,
                    icon: item.icon ? homeIcons[item.icon] : undefined,
                    title: item.title,
                    description: item.description,
                }))}
            />
        </Section>
    );
}

function SectionHeading({
    title,
    description,
}: {
    title: string | null;
    description?: string | null;
}) {
    if (!title && !description) {
        return null;
    }

    return (
        <Stack gap="tight" align="center">
            {title && (
                <Heading level={2} align="center">
                    {title}
                </Heading>
            )}
            {description && (
                <Text variant="lead" tone="muted" align="center">
                    {description}
                </Text>
            )}
        </Stack>
    );
}

function FaqSection({
    id,
    content,
}: {
    id: string;
    content: App.Data.Home.HomeFaqData;
}) {
    const { t } = useTranslation();

    return (
        <Section id={id} spacing="default" container="reading">
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

function TestimonialsSection({
    id,
    content,
}: {
    id: string;
    content: App.Data.Home.TestimonialsContentData;
}) {
    return (
        <Section id={id} spacing="relaxed" tone="subtle" container="wide">
            <Stack gap="relaxed">
                <SectionHeading title={content.title} />
                <Grid layout="cards">
                    {content.items.map((item, index) => (
                        <Surface
                            key={`${index}-${item.author}`}
                            as="article"
                            padding="default"
                            radius="lg"
                        >
                            <Stack gap="default">
                                <Text variant="lead" as="blockquote">
                                    {item.quote}
                                </Text>
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
                            </Stack>
                        </Surface>
                    ))}
                </Grid>
            </Stack>
        </Section>
    );
}

function LatestArticlesSection({
    id,
    content,
}: {
    id: string;
    content: App.Data.Home.HomeLatestArticlesData;
}) {
    const { t, formatDate } = useTranslation();

    return (
        <Section id={id} spacing="default" container="wide">
            <Stack gap="relaxed">
                <SectionHeading title={content.title} />
                {content.items.length === 0 ? (
                    <EmptyState title={t('home.latestArticlesEmpty')} />
                ) : (
                    <Grid layout="cards">
                        {content.items.map((article) => (
                            <Card key={article.url}>
                                <CardContent>
                                    <Stack gap="tight">
                                        {article.cover && (
                                            <Image
                                                src={article.cover.src}
                                                srcset={article.cover.srcset}
                                                sources={article.cover.sources}
                                                width={article.cover.width}
                                                height={article.cover.height}
                                                alt=""
                                                sizes="(min-width: 64rem) 22rem, (min-width: 40rem) 45vw, 100vw"
                                            />
                                        )}
                                        <Heading level={3} variant="group">
                                            <Link
                                                href={article.url}
                                                ariaLabel={t('home.readMore', {
                                                    title: article.title,
                                                })}
                                            >
                                                {article.title}
                                            </Link>
                                        </Heading>
                                        {article.publishedAt && (
                                            <Text
                                                variant="caption"
                                                tone="muted"
                                            >
                                                {formatDate(
                                                    article.publishedAt,
                                                    {
                                                        dateStyle: 'long',
                                                    },
                                                )}
                                            </Text>
                                        )}
                                        {article.excerpt && (
                                            <Text tone="muted">
                                                {article.excerpt}
                                            </Text>
                                        )}
                                    </Stack>
                                </CardContent>
                            </Card>
                        ))}
                    </Grid>
                )}
                <Stack align="center">
                    <Button variant="outline" href={content.listUrl}>
                        {t('home.allArticles')}
                    </Button>
                </Stack>
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
