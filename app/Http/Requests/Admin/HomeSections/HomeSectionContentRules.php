<?php

namespace App\Http\Requests\Admin\HomeSections;

use App\Data\Home\FaqContentData;
use App\Data\Home\FeaturesContentData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\HomeIcon;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionType;
use App\Enums\PublicationStatus;
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
                'content' => ['required', 'array:eyebrow,title,description,primaryAction,secondaryAction'],
                'content.eyebrow' => $this->text(60),
                'content.title' => $this->text(120, required: true),
                'content.description' => $this->text(500),
                ...$this->action('content.primaryAction', $locale),
                ...$this->action('content.secondaryAction', $locale),
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
                'content' => ['required', 'array:title,items'],
                'content.title' => $this->text(120),
                'content.items' => ['present', 'array', 'list', 'max:'.TestimonialsContentData::MAX_ITEMS],
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
            HomeSectionType::Contact => [
                'content' => ['required', 'array:title,description'],
                'content.title' => $this->text(120, required: true),
                'content.description' => $this->text(500),
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
