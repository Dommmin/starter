<?php

namespace App\Http\Requests\Admin\Faqs;

use App\Ai\Agents\FaqGeneratorAgent;
use App\Models\Faq;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entries the admin ticked among the assistant's FAQ proposals; they are
 * saved unpublished in one language.
 */
class StoreManyFaqsRequest extends FormRequest
{
    /**
     * The route checks `create`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Faq::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(LocalizationConfig $localization): array
    {
        $fields = StoreFaqRequest::fieldRules();

        return [
            'locale' => ['required', 'string', Rule::in($localization->getPublicLocales())],
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.FaqGeneratorAgent::MAX_ITEMS],
            'items.*' => ['required', 'array:question,answer'],
            'items.*.question' => $fields['question'],
            'items.*.answer' => $fields['answer'],
        ];
    }

    /**
     * Field values of each new faq, in the submitted order.
     *
     * @return list<array{question: string, answer: string, locale: string, position: null, published: false}>
     */
    public function faqs(): array
    {
        /** @var list<array{question: string, answer: string}> $items */
        $items = $this->validated('items');
        $locale = (string) $this->validated('locale');

        return array_map(fn (array $item): array => [
            'question' => $item['question'],
            'answer' => $item['answer'],
            'locale' => $locale,
            'position' => null,
            'published' => false,
        ], $items);
    }
}
