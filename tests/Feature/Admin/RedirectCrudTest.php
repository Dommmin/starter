<?php

use App\Enums\AuditAction;
use App\Enums\RedirectStatus;
use App\Models\AuditLog;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * A valid store/update payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function redirectPayload(array $overrides = []): array
{
    return [
        'source_path' => '/oferta/strony-www.html',
        'target' => '/offer',
        'status_code' => '301',
        'is_active' => true,
        ...$overrides,
    ];
}

function redirectCsv(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('redirects.csv', $content);
}

test('guests are redirected to the login page', function () {
    $this->get(route('admin.redirects.index'))->assertRedirect(route('login'));
});

test('the list exposes the typed list payload with the visit counter', function () {
    $admin = User::factory()->admin()->create();
    $redirect = Redirect::factory()->temporary()->create(['hits' => 4]);

    $this->actingAs($admin)->get(route('admin.redirects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/redirects/index', false)
            ->has('items', 1)
            ->where('items.0.id', $redirect->id)
            ->where('items.0.statusCode', 302)
            ->where('items.0.hits', 4)
            ->where('items.0.lastHitAt', null)
            ->where('filters', [
                'search' => '',
                'sort' => 'source_path',
                'direction' => 'asc',
                'is_active' => 'all',
            ])
            ->where('can', ['create' => true, 'delete' => true, 'import' => true])
        );
});

test('search matches the source and the target', function () {
    $admin = User::factory()->admin()->create();
    $bySource = Redirect::factory()->create(['source_path' => '/needle.html', 'target' => '/a']);
    $byTarget = Redirect::factory()->create(['source_path' => '/b.html', 'target' => '/needle']);
    Redirect::factory()->create(['source_path' => '/other.html', 'target' => '/c']);

    $this->actingAs($admin)->get(route('admin.redirects.index', ['search' => 'needle']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->has('items', 2)
            ->where('items.0.id', $byTarget->id)
            ->where('items.1.id', $bySource->id)
        );
});

test('sorting accepts only allowlisted columns', function () {
    $admin = User::factory()->admin()->create();
    $rare = Redirect::factory()->create(['hits' => 1]);
    $popular = Redirect::factory()->create(['hits' => 9]);

    $this->actingAs($admin)->get(route('admin.redirects.index', ['sort' => 'hits', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('items.0.id', $popular->id)
            ->where('items.1.id', $rare->id)
        );

    $this->actingAs($admin)->get(route('admin.redirects.index', ['sort' => 'target']))
        ->assertSessionHasErrors('sort');
});

test('an admin creates a redirect with normalized addresses, audited', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.redirects.store'), redirectPayload([
            'source_path' => 'https://old.example.com/Oferta//Strony-WWW.html/?page=2',
            'target' => '/offer/?utm_source=old',
            'status_code' => '302',
        ]))
        ->assertSessionHasNoErrors();

    $redirect = Redirect::query()->sole();
    expect($redirect->source_path)->toBe('/oferta/strony-www.html')
        ->and($redirect->target)->toBe('/offer?utm_source=old')
        ->and($redirect->status_code)->toBe(RedirectStatus::Temporary)
        ->and($redirect->is_active)->toBeTrue();

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::RedirectCreated)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->changes['source_path'])->toBe(['old' => null, 'new' => '/oferta/strony-www.html'])
        ->and($entry->changes['status_code'])->toBe(['old' => null, 'new' => 302]);
});

test('an update with the current version saves the values and audits only the changes', function () {
    $admin = User::factory()->admin()->create();
    $redirect = Redirect::factory()->create(['source_path' => '/old.html', 'target' => '/new']);

    $this->actingAs($admin)
        ->put(route('admin.redirects.update', $redirect), redirectPayload([
            'source_path' => '/old.html',
            'target' => 'https://partner.example.com/offer',
            'updated_at' => $redirect->updated_at?->toIso8601String(),
        ]))
        ->assertRedirect(route('admin.redirects.edit', $redirect));

    expect($redirect->refresh()->target)->toBe('https://partner.example.com/offer');

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::RedirectUpdated)
        ->and(array_keys($entry->changes ?? []))->toBe(['target']);
});

test('a stale version is rejected with a conflict error and nothing changes', function () {
    $admin = User::factory()->admin()->create();
    $redirect = Redirect::factory()->create(['target' => '/new']);

    $this->actingAs($admin)
        ->put(route('admin.redirects.update', $redirect), redirectPayload([
            'source_path' => $redirect->source_path,
            'updated_at' => $redirect->updated_at?->subMinute()->toIso8601String(),
        ]))
        ->assertSessionHasErrors('conflict');

    expect($redirect->refresh()->target)->toBe('/new')
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the source must be unique after normalization', function () {
    $admin = User::factory()->admin()->create();
    Redirect::factory()->create(['source_path' => '/oferta.html']);

    $this->actingAs($admin)
        ->post(route('admin.redirects.store'), redirectPayload(['source_path' => '/OFERTA.html/']))
        ->assertSessionHasErrors('source_path');

    expect(Redirect::query()->count())->toBe(1);
});

test('invalid addresses are rejected and nothing is stored', function (array $payload, string $field) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.redirects.store'), redirectPayload($payload))
        ->assertSessionHasErrors($field);

    expect(Redirect::query()->count())->toBe(0);
})->with([
    'protocol-relative target' => [['target' => '//evil.example.com/x'], 'target'],
    'javascript target' => [['target' => 'javascript:alert(1)'], 'target'],
    'target without a slash' => [['target' => 'offer'], 'target'],
    'home page source' => [['source_path' => '/'], 'source_path'],
    'panel source' => [['source_path' => '/admin/pages'], 'source_path'],
    'sign-in source' => [['source_path' => '/login'], 'source_path'],
    'unknown status' => [['status_code' => '307'], 'status_code'],
]);

test('a redirect to itself is rejected', function (string $target) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.redirects.store'), redirectPayload(['source_path' => '/offer.html', 'target' => $target]))
        ->assertSessionHasErrors(['target' => __('admin.redirects.errors.selfLoop')]);

    expect(Redirect::query()->count())->toBe(0);
})->with([
    'same path' => '/Offer.html/',
    'same path with a query' => '/offer.html?x=1',
    'absolute URL of this site' => 'http://localhost/offer.html',
]);

test('chains are rejected in both directions, so A→B→A cannot be built', function () {
    $admin = User::factory()->admin()->create();
    Redirect::factory()->create(['source_path' => '/a', 'target' => '/b']);

    // B→A would close the loop A→B→A; B is the target of A→B.
    $this->actingAs($admin)
        ->post(route('admin.redirects.store'), redirectPayload(['source_path' => '/b', 'target' => '/a']))
        ->assertSessionHasErrors(['target' => __('admin.redirects.errors.targetIsSource')]);

    // C→A would make the chain C→A→B.
    $this->actingAs($admin)
        ->post(route('admin.redirects.store'), redirectPayload(['source_path' => '/c', 'target' => '/a']))
        ->assertSessionHasErrors(['target' => __('admin.redirects.errors.targetIsSource')]);

    // B→D would make the chain A→B→D.
    $this->actingAs($admin)
        ->post(route('admin.redirects.store'), redirectPayload(['source_path' => '/b', 'target' => '/d']))
        ->assertSessionHasErrors(['target' => __('admin.redirects.errors.sourceIsTarget')]);

    expect(Redirect::query()->count())->toBe(1);
});

test('editing a redirect does not count it as its own chain', function () {
    $admin = User::factory()->admin()->create();
    $redirect = Redirect::factory()->create(['source_path' => '/a', 'target' => '/b']);

    $this->actingAs($admin)
        ->put(route('admin.redirects.update', $redirect), redirectPayload([
            'source_path' => '/a',
            'target' => '/c',
            'updated_at' => $redirect->updated_at?->toIso8601String(),
        ]))
        ->assertSessionHasNoErrors();

    expect($redirect->refresh()->target)->toBe('/c');
});

test('editors and viewers can neither open nor mutate redirects', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $redirect = Redirect::factory()->create();
    $original = $redirect->fresh()?->toArray();

    $this->actingAs($user)->get(route('admin.redirects.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.redirects.create'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.redirects.edit', $redirect))->assertForbidden();
    $this->actingAs($user)->post(route('admin.redirects.store'), redirectPayload())->assertForbidden();
    $this->actingAs($user)->put(route('admin.redirects.update', $redirect), redirectPayload([
        'updated_at' => $redirect->updated_at?->toIso8601String(),
    ]))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.redirects.destroy', $redirect))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.redirects.destroy-many'), ['ids' => [$redirect->id]])->assertForbidden();
    $this->actingAs($user)->post(route('admin.import.preview', 'redirects'), ['file' => redirectCsv("source,target\n/x,/y\n")])->assertForbidden();

    expect(Redirect::query()->count())->toBe(1)
        ->and($redirect->fresh()?->toArray())->toBe($original)
        ->and(AuditLog::query()->count())->toBe(0);
})->with(['editor', 'viewer']);

test('the panel navigation offers redirects to administrators only', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageRedirects', true));

    $this->actingAs(User::factory()->editor()->create())->get(route('admin.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageRedirects', false));
});

test('an admin deletes a redirect, audited', function () {
    $admin = User::factory()->admin()->create();
    $redirect = Redirect::factory()->create(['source_path' => '/gone.html']);

    $this->actingAs($admin)->delete(route('admin.redirects.destroy', $redirect))
        ->assertRedirect(route('admin.redirects.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.redirects.deleted')]);

    expect(Redirect::query()->count())->toBe(0)
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::RedirectDeleted);
});

test('an admin deletes the selected redirects and keeps the others', function () {
    $admin = User::factory()->admin()->create();
    [$first, $second, $kept] = Redirect::factory()->count(3)->create();

    $this->actingAs($admin)
        ->from(route('admin.redirects.index'))
        ->delete(route('admin.redirects.destroy-many'), ['ids' => [$first->id, $second->id, $second->id + 1000]])
        ->assertRedirect(route('admin.redirects.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.redirects.deletedMany', ['count' => 2])]);

    expect(Redirect::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(AuditLog::query()->where('action', AuditAction::RedirectDeleted)->count())->toBe(2);
});

test('a CSV import rejects chains inside the file and with stored redirects', function () {
    $admin = User::factory()->admin()->create();
    Redirect::factory()->create(['source_path' => '/stored', 'target' => '/home']);
    $csv = "source,target\n/a,/b\n/b,/a\n/x,/stored\n";

    $this->actingAs($admin)->post(route('admin.import.preview', 'redirects'), ['file' => redirectCsv($csv)])
        ->assertInertia(fn (Assert $page) => $page->where('preview.invalid', 3)->where('preview.token', null));

    expect(Redirect::query()->count())->toBe(1);
});

test('a clean CSV file imports normalized redirects with defaults and one audit entry', function () {
    $admin = User::factory()->admin()->create();
    $csv = "source,target,status_code,is_active\nhttps://old.example.com/O-nas.html,/about/,,\n/stara-oferta,https://partner.example.com/,302,nie\n";
    $token = null;

    $this->actingAs($admin)->post(route('admin.import.preview', 'redirects'), ['file' => redirectCsv($csv)])
        ->assertInertia(function (Assert $page) use (&$token) {
            $token = $page->toArray()['props']['preview']['token'];
        });

    $this->actingAs($admin)->post(route('admin.import.store', 'redirects'), ['token' => $token])
        ->assertRedirect(route('admin.redirects.index'));

    $about = Redirect::query()->where('source_path', '/o-nas.html')->sole();
    $offer = Redirect::query()->where('source_path', '/stara-oferta')->sole();
    expect($about->target)->toBe('/about')
        ->and($about->status_code)->toBe(RedirectStatus::Permanent)
        ->and($about->is_active)->toBeTrue()
        ->and($offer->status_code)->toBe(RedirectStatus::Temporary)
        ->and($offer->is_active)->toBeFalse()
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::ResourceImported);
});
