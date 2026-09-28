<?php

namespace Database\Seeders;

use App\Actions\Home\EnsureHomeSections;
use App\Data\Home\ContactContentData;
use App\Data\Home\CtaContentData;
use App\Data\Home\FaqContentData;
use App\Data\Home\FeatureItemData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\HomeActionData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\HomeIcon;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionType;
use Illuminate\Database\Seeder;
use Spatie\LaravelData\Data;

/**
 * Demo content of the home page: the former static landing page rebuilt from
 * `lang/{locale}/public.php` (`landing.*`) for every public locale. Hero,
 * features, contact and CTA start enabled; FAQ, testimonials and latest
 * articles start disabled. Idempotent: existing sections are kept as edited.
 */
class HomeSectionSeeder extends Seeder
{
    public function run(EnsureHomeSections $ensureHomeSections): void
    {
        $ensureHomeSections->handle(
            fn (HomeSectionType $type, string $locale): Data => self::landingContent($type, $locale),
            [HomeSectionType::Hero, HomeSectionType::Features, HomeSectionType::Contact, HomeSectionType::Cta],
        );
    }

    public static function landingContent(HomeSectionType $type, string $locale): Data
    {
        $t = fn (string $key): string => __($key, [], $locale);

        return match ($type) {
            HomeSectionType::Hero => new HeroContentData(
                title: $t('public.landing.heroTitle'),
                eyebrow: $t('public.landing.badge'),
                description: $t('public.landing.heroDescription'),
                primaryAction: new HomeActionData(label: $t('public.landing.ctaPrimaryGuest'), target: HomeLinkTarget::Register),
                secondaryAction: new HomeActionData(label: $t('common.nav.login'), target: HomeLinkTarget::Login),
            ),
            HomeSectionType::Features => new FeaturesContentData(
                items: [
                    new FeatureItemData(title: $t('public.landing.feature1Title'), description: $t('public.landing.feature1Desc'), icon: HomeIcon::Palette),
                    new FeatureItemData(title: $t('public.landing.feature2Title'), description: $t('public.landing.feature2Desc'), icon: HomeIcon::Lock),
                    new FeatureItemData(title: $t('public.landing.feature3Title'), description: $t('public.landing.feature3Desc'), icon: HomeIcon::Zap),
                    new FeatureItemData(title: $t('public.landing.feature4Title'), description: $t('public.landing.feature4Desc'), icon: HomeIcon::Accessibility),
                ],
                title: $t('public.landing.featuresHeading'),
                description: $t('public.landing.featuresSubheading'),
            ),
            HomeSectionType::Faq => new FaqContentData,
            HomeSectionType::Testimonials => new TestimonialsContentData,
            HomeSectionType::LatestArticles => new LatestArticlesContentData,
            HomeSectionType::Contact => new ContactContentData(
                title: $t('public.contact.title'),
                description: $t('public.contact.description'),
            ),
            HomeSectionType::Cta => new CtaContentData(
                title: $t('public.landing.ctaBottomTitle'),
                description: $t('public.landing.ctaBottomDescription'),
                primaryAction: new HomeActionData(label: $t('public.landing.ctaPrimaryGuest'), target: HomeLinkTarget::Register),
                secondaryAction: new HomeActionData(label: $t('common.nav.login'), target: HomeLinkTarget::Login),
            ),
        };
    }
}
