<?php

namespace App\Actions\Assistant;

use App\Actions\Assistant\Exceptions\AssistantBudgetExceeded;
use App\Actions\Assistant\Exceptions\AssistantUnavailable;
use App\Ai\Agents\MediaAltTextAgent;
use App\Enums\AssistantFeature;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Localization\LocalizationConfig;
use App\Services\Media\ImageVariantGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Files\StoredImage;

/**
 * Ask the vision agent for the alt text of a clean image in the default
 * public locale; nothing is saved. Quarantined, rejected and non-image
 * assets never reach the provider. The caller checks
 * {@see AssistantFeature::isAvailable()} and the user's ability first.
 */
class SuggestMediaAltText
{
    /**
     * Formats the vision providers accept (no AVIF).
     *
     * @var list<string>
     */
    private const array VISION_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const array VISION_VARIANT_FORMATS = ['webp', 'jpeg', 'png'];

    /**
     * Larger images are downscaled by the provider anyway; a smaller variant
     * costs fewer tokens.
     */
    private const int MAX_VARIANT_WIDTH = 1568;

    /**
     * Provider limit of one image (Anthropic: 5 MB).
     */
    private const int MAX_ORIGINAL_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly RunAssistantPrompt $assistant,
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * @param  User|null  $actor  Null for the queued run.
     * @return string|null Null when the asset cannot be described (no prompt was sent).
     *
     * @throws AssistantBudgetExceeded
     * @throws AssistantUnavailable
     */
    public function handle(MediaAsset $asset, ?User $actor): ?string
    {
        if (! $asset->hasPassedScan() || ! $asset->isImage()) {
            return null;
        }

        $image = $this->image($asset);

        if ($image === null) {
            return null;
        }

        $output = $this->assistant->handle(
            AssistantFeature::MediaAltText,
            new MediaAltTextAgent($this->localization->getPublicDefault()),
            'Write the alternative text of the attached image.',
            $asset,
            $actor,
            [$image],
        );

        $alt = Str::limit(Str::squish((string) ($output['alt'] ?? '')), MediaAltTextAgent::ALT_MAX, '');

        if ($alt === '') {
            throw new AssistantUnavailable;
        }

        return $alt;
    }

    /**
     * The largest public variant within the provider limits, or the private
     * original of a clean image when no variant exists yet.
     */
    private function image(MediaAsset $asset): ?StoredImage
    {
        $variants = collect($asset->variants ?? [])
            ->filter(fn (array $variant): bool => in_array($variant['format'], self::VISION_VARIANT_FORMATS, true)
                && $variant['width'] <= self::MAX_VARIANT_WIDTH)
            ->sortByDesc('width');

        foreach ($variants as $variant) {
            if (ImageVariantGenerator::publicDisk()->exists($variant['path'])) {
                return Image::fromStorage($variant['path'], (string) config('media.public_disk'));
            }
        }

        if (in_array($asset->mime, self::VISION_MIMES, true)
            && $asset->size <= self::MAX_ORIGINAL_BYTES
            && Storage::disk($asset->disk)->exists($asset->path)) {
            return Image::fromStorage($asset->path, $asset->disk);
        }

        return null;
    }
}
