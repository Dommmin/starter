<?php

namespace App\Data\Content;

use App\Enums\PublicationStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden;

/**
 * Validated input for one article translation, passed from the FormRequest
 * to the article actions. Validation rules live only in the FormRequest.
 */
#[Hidden]
class ArticleTranslationInputData extends Data
{
    /**
     * @param  array<string, mixed>|null  $body  Tiptap JSON document.
     * @param  CarbonImmutable|null  $publishedOn  Requested publication day (start of day, app timezone); null keeps the current date or publishes now.
     */
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public ?string $metaDescription,
        public ?array $body,
        public PublicationStatus $status,
        public ?CarbonImmutable $publishedOn,
    ) {}

    /**
     * Publication date to store, given the current one. An explicit day
     * replaces the current date unless it is the same day (keeps the time);
     * without a day, the first publication happens now and later saves keep
     * the recorded date.
     */
    public function resolvePublishedAt(?CarbonInterface $current): ?CarbonInterface
    {
        if ($this->publishedOn !== null) {
            return $current !== null && $current->isSameDay($this->publishedOn)
                ? $current
                : $this->publishedOn;
        }

        if ($this->status === PublicationStatus::Published && $current === null) {
            return now();
        }

        return $current;
    }
}
