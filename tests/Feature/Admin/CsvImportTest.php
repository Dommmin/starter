<?php

use App\Actions\Import\ImportCsv;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

function csvUpload(string $content, string $name = 'import.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

test('an administrator checks a FAQ file, nothing is saved, then imports exactly the checked rows', function () {
    $admin = User::factory()->admin()->create();
    $csv = "\u{FEFF}question;answer;locale;position;published\n"
        ."How long does delivery take?;Two days.;en;1;yes\n"
        ."Czy wysyłacie za granicę?;Tak, do UE.;pl;;nie\n"
        ."\n"
        ."Do you invoice?;Always.;;;\n";

    $response = $this->actingAs($admin)->post(route('admin.import.preview', 'faqs'), ['file' => csvUpload($csv)]);

    $token = null;
    $response->assertOk()->assertInertia(function (Assert $page) use (&$token) {
        $page->component('admin/import/show', false)
            ->where('module', 'faqs')
            ->where('preview.fileErrors', [])
            ->where('preview.total', 3)
            ->where('preview.invalid', 0)
            ->where('preview.rows.2.line', 5)
            ->where('preview.rows.0.label', 'How long does delivery take?');
        $token = $page->toArray()['props']['preview']['token'];
    });

    expect(Faq::query()->count())->toBe(0)->and($token)->toBeString();

    $this->actingAs($admin)->post(route('admin.import.store', 'faqs'), ['token' => $token])
        ->assertRedirect(route('admin.faqs.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.import.imported', ['count' => 3])]);

    $faqs = Faq::query()->orderBy('id')->get();
    expect($faqs->pluck('question')->all())->toBe(['How long does delivery take?', 'Czy wysyłacie za granicę?', 'Do you invoice?'])
        ->and($faqs[0]->position)->toBe(1)
        ->and($faqs[1]->published)->toBeFalse()
        ->and($faqs[2]->locale)->toBeNull()
        ->and($faqs[2]->published)->toBeTrue();

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::ResourceImported)
        ->and($audit->changes)->toBe(['module' => ['old' => null, 'new' => 'faqs'], 'rows' => ['old' => null, 'new' => 3]]);

    $this->actingAs($admin)->post(route('admin.import.store', 'faqs'), ['token' => $token])
        ->assertSessionHasErrors('token');
    expect(Faq::query()->count())->toBe(3);
});

test('invalid rows, including both copies of a duplicate, are reported with their line and block the import', function () {
    $admin = User::factory()->admin()->create();
    $csv = "question,answer,locale\n"
        ."Valid?,Yes.,en\n"
        ."Missing answer?,,en\n"
        ."Valid?,Duplicate question.,en\n"
        ."Unknown language?,Maybe.,xx\n";

    $this->actingAs($admin)->post(route('admin.import.preview', 'faqs'), ['file' => csvUpload($csv)])
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.total', 4)
            ->where('preview.invalid', 4)
            ->where('preview.token', null)
            ->where('preview.rows.0.line', 2)
            ->where('preview.rows.1.line', 3)
            ->where('preview.rows.2.line', 4)
            ->where('preview.rows.3.line', 5)
            ->has('preview.rows.0.errors', 1)
        );

    expect(Faq::query()->count())->toBe(0);
});

test('problems of the whole file are reported before any row', function (string $csv, string $message) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.import.preview', 'faqs'), ['file' => csvUpload($csv)])
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.fileErrors.0', $message)
            ->where('preview.token', null)
        );
})->with([
    'unknown column' => ["question,answer,colour\nA?,B.,red\n", 'Unknown columns: colour.'],
    'missing column' => ["question,locale\nA?,en\n", 'Missing columns: answer.'],
    'no data rows' => ["question,answer\n", 'The file has no data rows.'],
    'not UTF-8' => ["question,answer\n".mb_convert_encoding('Zażółć?,Tak.', 'ISO-8859-2', 'UTF-8')."\n", 'The file must be saved as UTF-8.'],
    'too many cells' => ["question,answer\nA?,B.,C\n", 'Line 2 has more cells than the header.'],
]);

test('a file with more rows than allowed is refused', function () {
    $admin = User::factory()->admin()->create();
    $csv = "question,answer\n".implode("\n", array_map(fn (int $i): string => "Q{$i}?,A.", range(1, ImportCsv::MAX_ROWS + 1)))."\n";

    $this->actingAs($admin)->post(route('admin.import.preview', 'faqs'), ['file' => csvUpload($csv)])
        ->assertInertia(fn (Assert $page) => $page->where('preview.fileErrors.0', 'The file has more than 1000 rows.'));
});

test('tags need new unique slugs and a name in the default language', function () {
    $admin = User::factory()->admin()->create();
    Tag::factory()->create(['slug' => 'existing']);
    $csv = "slug,name_en,name_pl\nnews,News,Aktualności\nexisting,Old,Stare\nnews,Again,Znowu\nguides,,Poradniki\n";

    $this->actingAs($admin)->post(route('admin.import.preview', 'tags'), ['file' => csvUpload($csv)])
        ->assertInertia(fn (Assert $page) => $page->where('preview.invalid', 4)->where('preview.token', null));

    $valid = "slug,name_en,name_pl\nnews,News,Aktualności\nguides,Guides,\n";
    $token = null;
    $this->actingAs($admin)->post(route('admin.import.preview', 'tags'), ['file' => csvUpload($valid)])
        ->assertInertia(function (Assert $page) use (&$token) {
            $token = $page->toArray()['props']['preview']['token'];
        });

    $this->actingAs($admin)->post(route('admin.import.store', 'tags'), ['token' => $token])->assertRedirect(route('admin.tags.index'));

    expect(Tag::query()->where('slug', 'news')->sole()->name)->toBe(['en' => 'News', 'pl' => 'Aktualności'])
        ->and(Tag::query()->where('slug', 'guides')->sole()->name)->toBe(['en' => 'Guides']);
});

test('data changed after the check is not imported', function () {
    $admin = User::factory()->admin()->create();
    $token = null;
    $this->actingAs($admin)->post(route('admin.import.preview', 'tags'), ['file' => csvUpload("slug,name_en\nnews,News\n")])
        ->assertInertia(function (Assert $page) use (&$token) {
            $token = $page->toArray()['props']['preview']['token'];
        });
    Tag::factory()->create(['slug' => 'news']);

    $this->actingAs($admin)->post(route('admin.import.store', 'tags'), ['token' => $token])
        ->assertSessionHasErrors(['token' => __('admin.import.errors.changed')]);

    expect(Tag::query()->count())->toBe(1);
});

test('a token works only for the user and module that checked the file', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $token = null;
    $this->actingAs($admin)->post(route('admin.import.preview', 'faqs'), ['file' => csvUpload("question,answer\nA?,B.\n")])
        ->assertInertia(function (Assert $page) use (&$token) {
            $token = $page->toArray()['props']['preview']['token'];
        });

    $this->actingAs($other)->post(route('admin.import.store', 'faqs'), ['token' => $token])->assertSessionHasErrors('token');
    $this->actingAs($admin)->post(route('admin.import.store', 'tags'), ['token' => $token])->assertSessionHasErrors('token');

    expect(Faq::query()->count())->toBe(0);
});

test('only administrators import, and the lists offer it only to them', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('admin.import.show', 'faqs'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.import.preview', 'faqs'), ['file' => csvUpload("question,answer\nA?,B.\n")])->assertForbidden();
    $this->actingAs($user)->post(route('admin.import.store', 'tags'), ['token' => str_repeat('a', 40)])->assertForbidden();
    $this->actingAs($user)->get(route('admin.faqs.index'))->assertInertia(fn (Assert $page) => $page->where('can.import', false));
    $this->actingAs($user)->get(route('admin.tags.index'))->assertInertia(fn (Assert $page) => $page->where('canImport', false));

    expect(Faq::query()->count())->toBe(0);
})->with(['editor', 'viewer']);

test('an administrator sees the import screen and the list buttons', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.import.show', 'tags'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns', ['slug', 'name_en', 'name_pl', 'name_de'])
            ->where('requiredColumns', ['slug', 'name_en'])
            ->where('preview', null)
        );
    $this->actingAs($admin)->get(route('admin.faqs.index'))->assertInertia(fn (Assert $page) => $page->where('can.import', true));
    $this->get('/admin/import/users')->assertNotFound();
});
