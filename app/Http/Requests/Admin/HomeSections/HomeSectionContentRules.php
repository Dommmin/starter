<?php

namespace App\Http\Requests\Admin\HomeSections;

use App\Data\Home\FaqContentData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\PortfolioContentData;
use App\Data\Home\PricingContentData;
use App\Data\Home\PricingPlanData;
use App\Data\Home\TeamContentData;
use App\Data\Home\TeamMemberData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\HomeIcon;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionType;
use App\Enums\PublicationStatus;
use App\Rules\DamImage;
use App\Services\Home\HomeLinkResolver;
use Illuminate\Validation\Rule;

/**
 * Validation of `content` per section type: the closed key set of every
 * object (`array:` rules reject unknown keys with 422), string lengths and
 * item limits. Content is plain text; markup is rejected.
 */
final class HomeSectionContentRules
{
    /** Rejects anything that looks like an HTML/XML tag. */
    private const string NO_MARKUP = 'not_regex:/<\s*[\/!?a-zA-Z]/';

    public function __construct(
        private readonly HomeLinkResolver $links,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(HomeSectionType $type, string $locale): array
    {
        return match ($type) {
            HomeSectionType::Hero => [
                'content' => ['required', 'array:eyebrow,title,description,primaryAction,secondaryAction,imageId,stats'],
                'content.eyebrow' => $this->text(60),
                'content.title' => $this->text(120, required: true),
                'content.description' => $this->text(500),
                ...$this->action('content.primaryAction', $locale),
                ...$this->action('content.secondaryAction', $locale),
                'content.imageId' => $this->image(),
                'content.stats' => ['present', 'array', 'list', 'max:'.HeroContentData::MAX_STATS],
                'content.stats.*' => ['required', 'array:value,label'],
                'content.stats.*.value' => $this->text(12, required: true),
                'content.stats.*.label' => $this->text(60, required: true),
            ],
            HomeSectionType::Cta => [
                'content' => ['required', 'array:title,description,primaryAction,secondaryAction'],
                'content.title' => $this->text(120, required: true),
                'content.description' => $this->text(500),
                ...$this->action('content.primaryAction', $locale),
                ...$this->action('content.secondaryAction', $locale),
            ],
            HomeSectionType::Features => [
                'content' => ['required', 'array:title,description,items'],
                'content.title' => $this->text(120),
                'content.description' => $this->text(500),
                'content.items' => ['present', 'array', 'list', 'max:'.FeaturesContentData::MAX_ITEMS],
                'content.items.*' => ['required', 'array:icon,title,description'],
                'content.items.*.icon' => ['nullable', Rule::enum(HomeIcon::class)],
                'content.items.*.title' => $this->text(80, required: true),
                'content.items.*.description' => $this->text(300, required: true),
            ],
            HomeSectionType::Testimonials => [
                'content' => ['required', 'array:title,items,stats'],
                'content.title' => $this->text(120),
                'content.items' => ['present', 'array', 'list', 'max:'.TestimonialsContentData::MAX_ITEMS],
                'content.stats' => ['sometimes', 'array', 'list', 'max:'.TestimonialsContentData::MAX_STATS],
                'content.stats.*' => ['required', 'array:value,label'],
                'content.stats.*.value' => $this->text(12, required: true),
                'content.stats.*.label' => $this->text(60, required: true),
                'content.items.*' => ['required', 'array:author,role,quote'],
                'content.items.*.author' => $this->text(80, required: true),
                'content.items.*.role' => $this->text(80),
                'content.items.*.quote' => $this->text(600, required: true),
            ],
            HomeSectionType::Faq => [
                'content' => ['required', 'array:title,description,limit'],
                'content.title' => $this->text(120),
                'content.description' => $this->text(500),
                'content.limit' => ['nullable', 'integer', 'min:1', 'max:'.FaqContentData::MAX_LIMIT],
            ],
            HomeSectionType::LatestArticles => [
                'content' => ['required', 'array:title,limit'],
                'content.title' => $this->text(120),
                'content.limit' => ['required', 'integer', 'min:1', 'max:'.LatestArticlesContentData::MAX_LIMIT],
            ],
            HomeSectionType::Portfolio => [
                'content' => ['required', 'array:title,description,items'],
                'content.title' => $this->text(120),
                'content.description' => $this->text(500),
                'content.items' => ['present', 'array', 'list', 'max:'.PortfolioContentData::MAX_ITEMS],
                'content.items.*' => ['required', 'array:title,category,description,imageId,url'],
                'content.items.*.title' => $this->text(80, required: true),
                'content.items.*.category' => $this->text(40),
                'content.items.*.description' => $this->text(300),
                'content.items.*.imageId' => $this->image(),
                'content.items.*.url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            ],
            HomeSectionType::Team => [
                'content' => ['required', 'array:title,description,items'],
                'content.title' => $this->text(120),
                'content.description' => $this->text(500),
                'content.items' => ['present', 'array', 'list', 'max:'.TeamContentData::MAX_ITEMS],
                'content.items.*' => ['required', 'array:name,role,bio,photoId,skills'],
                'content.items.*.name' => $this->text(80, required: true),
                'content.items.*.role' => $this->text(80, required: true),
                'content.items.*.bio' => $this->text(400),
                'content.items.*.photoId' => $this->image(),
                'content.items.*.skills' => ['present', 'array', 'list', 'max:'.TeamMemberData::MAX_SKILLS],
                'content.items.*.skills.*' => $this->text(40, required: true),
            ],
            HomeSectionType::Pricing => [
                'content' => ['required', 'array:title,description,note,items'],
                'content.title' => $this->text(120),
                'content.description' => $this->text(500),
                'content.note' => $this->text(300),
                'content.items' => ['present', 'array', 'list', 'max:'.PricingContentData::MAX_ITEMS],
                'content.items.*' => ['required', 'array:name,priceMinor,currency,priceFrom,period,description,features,highlighted,action'],
                'content.items.*.name' => $this->text(60, required: true),
                'content.items.*.priceMinor' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
                'content.items.*.currency' => ['required', Rule::in(PricingContentData::CURRENCIES)],
                'content.items.*.priceFrom' => ['required', 'boolean'],
                'content.items.*.period' => $this->text(30),
                'content.items.*.description' => $this->text(300),
                'content.items.*.features' => ['present', 'array', 'list', 'max:'.PricingPlanData::MAX_FEATURES],
                'content.items.*.features.*' => $this->text(120, required: true),
                'content.items.*.highlighted' => ['required', 'boolean'],
                ...$this->action('content.items.*.action', $locale),
            ],
            HomeSectionType::Contact => [
                'content' => ['required', 'array:title,description,formKey'],
                'content.title' => $this->text(120, required: true),
                'content.description' => $this->text(500),
                'content.formKey' => ['nullable', 'string', Rule::exists('contact_forms', 'key')],
            ],
        };
    }

    /**
     * @return list<mixed>
     */
    private function text(int $max, bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:'.$max, self::NO_MARKUP];
    }

    /**
     * An optional DAM image: the id of a clean image with variants.
     *
     * @return list<mixed>
     */
    private function image(): array
    {
        return ['nullable', 'integer', new DamImage];
    }

    /**
     * An optional action: a label and an available target; `pageId` only
     * for the `page` target and only a page published in the locale.
     *
     * @return array<string, list<mixed>>
     */
    private function action(string $prefix, string $locale): array
    {
        return [
            $prefix => ['nullable', 'array:label,target,pageId'],
            "{$prefix}.label" => ["required_with:{$prefix}", 'string', 'max:40', self::NO_MARKUP],
            "{$prefix}.target" => ["required_with:{$prefix}", Rule::in(array_map(
                fn (HomeLinkTarget $target): string => $target->value,
                $this->links->availableTargets(),
            ))],
            "{$prefix}.pageId" => [
                'nullable',
                'integer',
                "required_if:{$prefix}.target,".HomeLinkTarget::Page->value,
                "prohibited_unless:{$prefix}.target,".HomeLinkTarget::Page->value,
                Rule::exists('page_translations', 'page_id')
                    ->where('locale', $locale)
                    ->where('status', PublicationStatus::Published->value),
            ],
        ];
    }
}
