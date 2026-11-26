<?php

namespace App\Rules;

use App\Repositories\Redirects\RedirectRepository;
use App\Support\Redirects\RedirectPath;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validate the normalized `target` of a redirect against its sibling
 * `source_path`: a relative path or http(s) URL, never the source itself
 * and never part of a chain, so every redirect is a single hop and loops of
 * any length are impossible. A chain is a target that is the source of
 * another redirect, or a source that another redirect points at. Other
 * redirects are all stored ones except `$ignoreId` and, in a CSV import
 * (`rows.*.target`), the other rows of the file.
 */
class RedirectTarget implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array{sources: list<string>, targets: list<string>}|null Stored redirects, loaded once per validation. */
    private ?array $stored = null;

    public function __construct(private readonly ?int $ignoreId = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! RedirectPath::isValidTarget($value)) {
            $fail(__('admin.redirects.errors.targetFormat'));

            return;
        }

        $source = data_get($this->data, (string) preg_replace('/target$/', 'source_path', $attribute));
        if (! is_string($source)) {
            return;
        }

        $targetPath = RedirectPath::localTargetPath($value);
        if ($targetPath === $source) {
            $fail(__('admin.redirects.errors.selfLoop'));

            return;
        }

        ['sources' => $sources, 'targets' => $targets] = $this->otherEndpoints($attribute);

        if ($targetPath !== null && in_array($targetPath, $sources, true)) {
            $fail(__('admin.redirects.errors.targetIsSource'));
        } elseif (in_array($source, $targets, true)) {
            $fail(__('admin.redirects.errors.sourceIsTarget'));
        }
    }

    /**
     * @return array{sources: list<string>, targets: list<string>}
     */
    private function otherEndpoints(string $attribute): array
    {
        $endpoints = $this->stored ??= app(RedirectRepository::class)->chainEndpoints($this->ignoreId);

        if (preg_match('/^rows\.(\d+)\.target$/', $attribute, $match) !== 1 || ! is_array($this->data['rows'] ?? null)) {
            return $endpoints;
        }

        foreach ($this->data['rows'] as $index => $row) {
            if ((string) $index === $match[1] || ! is_array($row)) {
                continue;
            }

            if (is_string($row['source_path'] ?? null)) {
                $endpoints['sources'][] = $row['source_path'];
            }

            $targetPath = is_string($row['target'] ?? null) ? RedirectPath::localTargetPath($row['target']) : null;
            if ($targetPath !== null) {
                $endpoints['targets'][] = $targetPath;
            }
        }

        return $endpoints;
    }
}
