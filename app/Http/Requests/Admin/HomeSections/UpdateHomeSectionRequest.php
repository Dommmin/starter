<?php

namespace App\Http\Requests\Admin\HomeSections;

use App\Models\HomeSection;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\Data;

class UpdateHomeSectionRequest extends FormRequest
{
    /**
     * The route checks `update`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        $section = $this->route('homeSection');

        return $section instanceof HomeSection && ($this->user()?->can('update', $section) ?? false);
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic
     * locking); `content` follows the closed schema of the section type.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $section = $this->section();

        return [
            'updated_at' => ['required', 'string', 'date'],
            ...app(HomeSectionContentRules::class)->rules($section->type, $section->locale),
        ];
    }

    /**
     * Validated content as the Data class of the section type; only keys
     * with rules survive `validated()`.
     */
    public function content(): Data
    {
        /** @var array<string, mixed> $content */
        $content = $this->validated('content');

        return $this->section()->type->contentClass()::from($content);
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }

    private function section(): HomeSection
    {
        /** @var HomeSection $section */
        $section = $this->route('homeSection');

        return $section;
    }
}
