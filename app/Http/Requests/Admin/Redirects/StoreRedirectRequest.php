<?php

namespace App\Http\Requests\Admin\Redirects;

use App\Enums\RedirectStatus;
use App\Models\Redirect;
use App\Rules\RedirectTarget;
use App\Support\Redirects\RedirectPath;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRedirectRequest extends FormRequest
{
    /**
     * The route checks `create`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Redirect::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Validated field values ready to be stored.
     *
     * @return array{source_path: string, target: string, status_code: int, is_active: bool}
     */
    public function fieldValues(): array
    {
        /** @var array{source_path: string, target: string, status_code: int, is_active: bool} */
        return $this->safe()->only(['source_path', 'target', 'status_code', 'is_active']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalizedInput($this->all()));
    }

    /**
     * Source and target in their stored form (validation and uniqueness
     * then apply to the normalized values) and a numeric status code.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizedInput(array $input): array
    {
        $normalized = [];

        if (is_string($input['source_path'] ?? null) && trim($input['source_path']) !== '') {
            $normalized['source_path'] = RedirectPath::source($input['source_path']);
        }

        if (is_string($input['target'] ?? null)) {
            $normalized['target'] = RedirectPath::target($input['target']);
        }

        if (is_string($input['status_code'] ?? null) && ctype_digit($input['status_code'])) {
            $normalized['status_code'] = (int) $input['status_code'];
        }

        return $normalized;
    }

    /**
     * Validation of the editable fields, shared with UpdateRedirectRequest
     * and the CSV import; `$ignoreId` is the redirect being edited.
     *
     * @return array<string, list<mixed>>
     */
    public static function fieldRules(?int $ignoreId = null): array
    {
        return [
            'source_path' => ['required', 'string', 'max:512', self::sourceRule(), Rule::unique('redirects', 'source_path')->ignore($ignoreId)],
            'target' => ['required', 'string', 'max:2048', new RedirectTarget($ignoreId)],
            'status_code' => ['required', 'integer', Rule::enum(RedirectStatus::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * The home page and panel, settings, health and sign-in paths are
     * never redirected.
     */
    public static function sourceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if ($value === '/') {
                $fail(__('admin.redirects.errors.sourceHome'));
            } elseif (RedirectPath::isReserved($value)) {
                $fail(__('admin.redirects.errors.sourceReserved'));
            }
        };
    }
}
