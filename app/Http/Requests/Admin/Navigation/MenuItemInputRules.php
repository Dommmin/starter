<?php

namespace App\Http\Requests\Admin\Navigation;

use App\Data\Navigation\MenuItemInputData;
use App\Enums\MenuItemType;
use App\Services\Navigation\PublicNavigation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Field validation of a menu item shared by the store and update requests.
 * Structural rules that depend on other rows (parent depth, menu, children)
 * are checked by the actions under a lock.
 */
final class MenuItemInputRules
{
    public const string ANCHOR_PATTERN = '/^[a-z0-9-]+$/';

    /**
     * Optional inputs whose blank value means "none".
     *
     * @var list<string>
     */
    private const array BLANKABLE = ['parent_id', 'page_id', 'article_id', 'anchor', 'url', 'label'];

    /**
     * Null for each optional input sent as a blank string.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, null>
     */
    public static function blankInputsAsNull(array $input): array
    {
        $blank = [];
        foreach (self::BLANKABLE as $name) {
            if (is_string($input[$name] ?? null) && trim($input[$name]) === '') {
                $blank[$name] = null;
            }
        }

        return $blank;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(FormRequest $request): array
    {
        $type = MenuItemType::tryFrom($request->string('type')->toString());
        $is = fn (MenuItemType ...$types): bool => $type !== null && in_array($type, $types, true);

        return [
            'parent_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(MenuItemType::class)],
            'page_id' => [
                'nullable',
                Rule::requiredIf($is(MenuItemType::Page)),
                Rule::prohibitedIf(! $is(MenuItemType::Page, MenuItemType::Anchor)),
                'integer',
                Rule::exists('pages', 'id'),
            ],
            'article_id' => [
                'nullable',
                Rule::requiredIf($is(MenuItemType::Article)),
                Rule::prohibitedIf(! $is(MenuItemType::Article)),
                'integer',
                Rule::exists('articles', 'id'),
            ],
            'anchor' => [
                'nullable',
                Rule::requiredIf($is(MenuItemType::Anchor)),
                Rule::prohibitedIf(! $is(MenuItemType::Anchor)),
                'string',
                'max:64',
                'regex:'.self::ANCHOR_PATTERN,
            ],
            'url' => [
                'nullable',
                Rule::requiredIf($is(MenuItemType::External)),
                Rule::prohibitedIf(! $is(MenuItemType::External)),
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! PublicNavigation::isSafeExternalUrl($value)) {
                        $fail(__('validation.navigation.url_scheme'));
                    }
                },
            ],
            'label' => [
                'nullable',
                Rule::requiredIf($type?->requiresLabel() ?? false),
                'string',
                'max:120',
            ],
            'open_in_new_tab' => [
                'required',
                'boolean',
                function (string $attribute, mixed $value, Closure $fail) use ($is): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN) && ! $is(MenuItemType::External)) {
                        $fail(__('validation.navigation.new_tab_external'));
                    }
                },
            ],
        ];
    }

    public static function input(FormRequest $request): MenuItemInputData
    {
        $type = MenuItemType::from($request->string('type')->toString());
        $label = $request->string('label')->trim()->toString();

        return new MenuItemInputData(
            parentId: $request->filled('parent_id') ? $request->integer('parent_id') : null,
            type: $type,
            pageId: $request->filled('page_id') ? $request->integer('page_id') : null,
            articleId: $request->filled('article_id') ? $request->integer('article_id') : null,
            anchor: $request->filled('anchor') ? $request->string('anchor')->toString() : null,
            url: $request->filled('url') ? $request->string('url')->toString() : null,
            label: $label === '' ? null : $label,
            openInNewTab: $type === MenuItemType::External && $request->boolean('open_in_new_tab'),
        );
    }
}
