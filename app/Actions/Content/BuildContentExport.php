<?php

namespace App\Actions\Content;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\ContentBlock;
use App\Models\Faq;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The content of the site as one array for a JSON download, so the owner can
 * take it elsewhere. It is not a backup: media files, settings and accounts
 * are missing and nothing imports this file.
 *
 * Every field is listed on purpose. Accounts, form submissions, audit
 * entries, settings with secrets and trashed records are never included.
 */
class BuildContentExport
{
    public const int VERSION = 1;

    private const array TRANSLATION = ['locale', 'title', 'slug', 'meta_description', 'body', 'status', 'published_at'];

    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $actor): array
    {
        $export = [
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'pages' => Page::query()->with(['translations', 'sections' => fn ($query) => $query->orderBy('locale')->orderBy('position')])->orderBy('id')->get()
                ->map(fn (Page $page): array => [
                    ...$this->fields($page, ['id', 'layout_mode']),
                    'translations' => $this->list($page->translations, self::TRANSLATION),
                    'sections' => $this->list($page->sections, ['locale', 'type', 'position', 'enabled', 'content', 'content_block_id']),
                ])->all(),
            'articles' => Article::query()->with(['translations', 'tags'])->orderBy('id')->get()
                ->map(fn (Article $article): array => [
                    ...$this->fields($article, ['id', 'cover_media_id']),
                    'translations' => $this->list($article->translations, [...self::TRANSLATION, 'excerpt']),
                    'tag_ids' => $article->tags->modelKeys(),
                ])->all(),
            'tags' => $this->list(Tag::query()->orderBy('id')->get(), ['id', 'slug', 'name']),
            'faqs' => $this->list(Faq::query()->orderBy('locale')->orderBy('position')->get(), ['id', 'locale', 'question', 'answer', 'position', 'published']),
            'content_blocks' => $this->list(ContentBlock::query()->orderBy('id')->get(), ['id', 'name', 'locale', 'type', 'content']),
            'menu_items' => $this->list(MenuItem::query()->orderBy('id')->get(), ['id', 'parent_id', 'location', 'locale', 'position', 'type', 'page_id', 'article_id', 'anchor', 'url', 'label', 'open_in_new_tab']),
            'redirects' => $this->list(Redirect::query()->orderBy('id')->get(), ['source_path', 'target', 'status_code', 'is_active']),
            // Descriptions only; the files stay on the media disk.
            'media' => $this->list(MediaAsset::query()->orderBy('id')->get(), ['id', 'original_name', 'mime', 'size', 'width', 'height', 'alt']),
        ];

        // The audit log needs a subject; the export concerns the whole site.
        $this->audit->handle(AuditAction::ResourceExported, SiteSetting::query()->find(SiteSetting::SINGLETON_ID) ?? new SiteSetting, $actor, [
            'resource' => RecordAuditEvent::change(null, 'content'),
        ]);

        return $export;
    }

    /**
     * @param  iterable<int, Model>  $models
     * @param  list<string>  $fields
     * @return list<array<string, mixed>>
     */
    private function list(iterable $models, array $fields): array
    {
        $rows = [];
        foreach ($models as $model) {
            $rows[] = $this->fields($model, $fields);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function fields(Model $model, array $fields): array
    {
        $row = [];
        foreach ($fields as $field) {
            $row[$field] = $model->getAttribute($field);
        }

        return $row;
    }
}
