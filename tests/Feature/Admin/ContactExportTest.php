<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\User;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('an administrator downloads the filtered list as CSV with formulas neutralised and an audit entry', function () {
    $admin = User::factory()->admin()->create();
    ContactMessage::factory()->create(['name' => 'Ada', 'email' => 'ada@example.test', 'message' => '=HYPERLINK("http://evil.test")']);
    ContactMessage::factory()->failed()->create(['name' => 'Bob']);

    $response = $this->actingAs($admin)
        ->get(route('admin.contact.export', ['search' => 'Ada']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();

    $csv = $response->streamedContent();

    expect($csv)->toContain('ada@example.test')
        ->toContain("'=HYPERLINK")
        ->not->toContain('Bob');

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::ResourceExported)
        ->and($audit->changes['rows']['new'])->toBe(1)
        ->and($audit->changes['search'])->toBe(['redacted' => true]);
});

test('editors cannot export contact messages', function () {
    ContactMessage::factory()->create();

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.contact.export'))
        ->assertForbidden();

    expect(AuditLog::query()->exists())->toBeFalse();
});

test('an export above the row limit is refused without a download', function () {
    config(['exports.contact.max_rows' => 1]);
    ContactMessage::factory()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.contact.export'))
        ->assertStatus(422);

    expect(AuditLog::query()->exists())->toBeFalse();
});
