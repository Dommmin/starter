<?php

namespace Database\Factories;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\Article;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * An external link in the header of the default public locale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location' => MenuLocation::Header,
            'locale' => config('localization.public_default', 'en'),
            'parent_id' => null,
            'position' => 1,
            'type' => MenuItemType::External,
            'page_id' => null,
            'article_id' => null,
            'anchor' => null,
            'url' => 'https://example.com/'.fake()->unique()->slug(2),
            'label' => ucfirst(fake()->word()).' '.fake()->word(),
            'open_in_new_tab' => false,
        ];
    }

    /**
     * Place the item in the given menu.
     */
    public function in(MenuLocation $location, string $locale): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => $location,
            'locale' => $locale,
        ]);
    }

    /**
     * Make the item a child of the given first-level item (same menu).
     */
    public function childOf(MenuItem $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => $parent->location,
            'locale' => $parent->locale,
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Link to a page; an empty label falls back to the page title.
     */
    public function page(Page $page, ?string $label = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MenuItemType::Page,
            'page_id' => $page->id,
            'url' => null,
            'label' => $label,
        ]);
    }

    /**
     * Link to an article; an empty label falls back to the article title.
     */
    public function article(Article $article, ?string $label = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MenuItemType::Article,
            'article_id' => $article->id,
            'url' => null,
            'label' => $label,
        ]);
    }

    /**
     * Link to the public article list.
     */
    public function articleIndex(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MenuItemType::ArticleIndex,
            'url' => null,
        ]);
    }

    /**
     * Fragment on the home page, or on the given page.
     */
    public function anchor(string $anchor, ?Page $page = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MenuItemType::Anchor,
            'anchor' => $anchor,
            'page_id' => $page?->id,
            'url' => null,
        ]);
    }

    /**
     * First-level column heading without a link.
     */
    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MenuItemType::Group,
            'url' => null,
        ]);
    }
}
