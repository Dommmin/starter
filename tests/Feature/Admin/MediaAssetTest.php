<?php

use App\Enums\AuditAction;
use App\Enums\MediaStatus;
use App\Jobs\ScanMediaAsset;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\MediaFixtures;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
    Storage::fake('media');
    Storage::fake('public');
    Queue::fake();
});

/**
 * Store a real file on the faked private disk for the given asset.
 */
function putMediaOriginal(MediaAsset $asset, string $bytes = 'original-bytes'): void
{
    Storage::disk('media')->put($asset->path, $bytes);
}

test('guests are redirected to the login page', function () {
    $this->get(route('admin.media.index'))->assertRedirect(route('login'));
    $this->post(route('admin.media.store'))->assertRedirect(route('login'));
});

test('users without a panel role cannot list, upload or download', function () {
    $user = User::factory()->create();
    $asset = MediaAsset::factory()->clean()->create();
    putMediaOriginal($asset);

    $this->actingAs($user)->get(route('admin.media.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.media.download', $asset))->assertForbidden();
    $this->actingAs($user)
        ->post(route('admin.media.store'), ['file' => MediaFixtures::upload('photo.jpg', MediaFixtures::jpegBytes())])
        ->assertForbidden();

    expect(MediaAsset::query()->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('an editor uploads an image into quarantine and the scan is queued', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->from(route('admin.media.index'))
        ->post(route('admin.media.store'), [
            'file' => MediaFixtures::upload('../../Holiday <script>photo.JPG', MediaFixtures::jpegBytes(40, 20)),
        ])
        ->assertRedirect(route('admin.media.index'))
        ->assertSessionHasNoErrors();

    $asset = MediaAsset::query()->sole();

    expect($asset->status)->toBe(MediaStatus::Quarantine)
        ->and($asset->owner_id)->toBe($editor->id)
        ->and($asset->mime)->toBe('image/jpeg')
        ->and($asset->original_name)->toBe('Holiday -script-photo.jpg')
        ->and($asset->path)->toBe("{$asset->uuid}/original.jpg")
        ->and($asset->width)->toBe(40)
        ->and($asset->height)->toBe(20)
        ->and($asset->checksum)->toBe(hash('sha256', Storage::disk('media')->get($asset->path)))
        ->and($asset->variants)->toBeNull();

    Storage::disk('media')->assertExists($asset->path);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    Queue::assertPushed(ScanMediaAsset::class, fn (ScanMediaAsset $job): bool => $job->mediaAssetId === $asset->id);

    $audit = AuditLog::query()->where('action', AuditAction::MediaUploaded)->sole();
    expect($audit->actor_id)->toBe($editor->id)
        ->and($audit->subject_id)->toBe($asset->id);
});

test('a pdf upload is accepted as a document', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.media.store'), ['file' => MediaFixtures::upload('report.pdf', MediaFixtures::pdfBytes())])
        ->assertSessionHasNoErrors();

    $asset = MediaAsset::query()->sole();
    expect($asset->mime)->toBe('application/pdf')
        ->and($asset->width)->toBeNull()
        ->and($asset->isImage())->toBeFalse();
});

test('uploads are validated by content, extension and size', function (callable $file, string $message) {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->post(route('admin.media.store'), ['file' => $file()])
        ->assertSessionHasErrors(['file' => __($message, ['max' => 50])]);

    expect(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('media')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
})->with([
    'php disguised as jpg' => [fn () => MediaFixtures::upload('shell.jpg', "<?php system(\$_GET['c']); ?>"), 'admin.media.validation.type'],
    'html disguised as png' => [fn () => MediaFixtures::upload('page.png', '<html><script>alert(1)</script></html>'), 'admin.media.validation.type'],
    'jpeg with a pdf extension' => [fn () => MediaFixtures::upload('photo.pdf', MediaFixtures::jpegBytes()), 'admin.media.validation.extensionMismatch'],
    'svg' => [fn () => MediaFixtures::upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'), 'admin.media.validation.type'],
    'too large' => [fn () => UploadedFile::fake()->create('big.pdf', 51_201, 'application/pdf'), 'admin.media.validation.tooLarge'],
]);

test('an upload without a file is rejected', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.media.store'), [])
        ->assertSessionHasErrors(['file' => __('admin.media.validation.required')]);
});

test('an image header that exceeds the pixel budget is rejected', function () {
    config(['media.max_pixels' => 100]);

    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.media.store'), ['file' => MediaFixtures::upload('huge.jpg', MediaFixtures::jpegBytes(40, 20))])
        ->assertSessionHasErrors(['file' => __('admin.media.validation.unreadable')]);

    expect(MediaAsset::query()->count())->toBe(0);
});

test('the list exposes the typed payload with thumbnails only for clean images', function () {
    $editor = User::factory()->editor()->create();
    $clean = MediaAsset::factory()->withVariants()->create(['original_name' => 'clean.jpg']);
    $quarantined = MediaAsset::factory()->create(['original_name' => 'waiting.jpg', 'scan_error' => 'clamd connection failed (111).']);

    $this->actingAs($editor)->get(route('admin.media.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/media/index', false)
            ->has('items', 2)
            ->where('filters', ['search' => '', 'sort' => 'created_at', 'direction' => 'desc', 'status' => 'all', 'type' => 'all'])
            ->where('can', ['create' => true, 'update' => true, 'delete' => false])
            ->where('upload.maxBytes', 51_200 * 1024)
            ->where('items', fn ($items): bool => collect($items)->firstWhere('id', $clean->id)['thumbnailUrl'] === Storage::disk('public')->url("media/{$clean->uuid}/abc123-320.jpg")
                && collect($items)->firstWhere('id', $quarantined->id)['thumbnailUrl'] === null
                && collect($items)->firstWhere('id', $quarantined->id)['hasScanError'] === true)
        );
});

test('the list filters by status, type and name', function () {
    $admin = User::factory()->admin()->create();
    MediaAsset::factory()->clean()->create(['original_name' => 'needle.jpg']);
    MediaAsset::factory()->clean()->pdf()->create(['original_name' => 'needle.pdf']);
    MediaAsset::factory()->rejected()->create(['original_name' => 'other.jpg']);

    $this->actingAs($admin)->get(route('admin.media.index', ['search' => 'needle', 'type' => 'document']))
        ->assertInertia(fn (Assert $inertia) => $inertia->has('items', 1)->where('items.0.originalName', 'needle.pdf'));

    $this->actingAs($admin)->get(route('admin.media.index', ['status' => 'rejected']))
        ->assertInertia(fn (Assert $inertia) => $inertia->has('items', 1)->where('items.0.originalName', 'other.jpg'));

    $this->actingAs($admin)->get(route('admin.media.index', ['status' => 'infected']))
        ->assertSessionHasErrors('status');
});

test('only clean originals are downloadable, as attachments with nosniff', function () {
    $editor = User::factory()->editor()->create();
    $clean = MediaAsset::factory()->clean()->pdf()->create(['original_name' => 'report.pdf']);
    putMediaOriginal($clean, MediaFixtures::pdfBytes());

    $response = $this->actingAs($editor)->get(route('admin.media.download', $clean));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('report.pdf');
    expect($response->headers->get('Content-Security-Policy'))->toContain('sandbox');

    foreach ([MediaAsset::factory()->create(), MediaAsset::factory()->rejected()->create()] as $blocked) {
        putMediaOriginal($blocked);

        $this->actingAs($editor)->get(route('admin.media.download', $blocked))->assertNotFound();
        $this->actingAs($editor)->get(route('admin.media.preview', $blocked))->assertNotFound();
    }
});

test('the details screen never exposes urls of a quarantined file', function () {
    $editor = User::factory()->editor()->create();
    $asset = MediaAsset::factory()->create(['variants' => [['format' => 'jpeg', 'width' => 320, 'height' => 180, 'path' => 'media/x/leak-320.jpg', 'size' => 10]]]);

    $this->actingAs($editor)->get(route('admin.media.edit', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/media/edit', false)
            ->where('asset.status', 'quarantine')
            ->where('asset.image', null)
            ->where('asset.variants', [])
            ->where('asset.downloadUrl', null)
            ->where('can.delete', false)
        );
});

test('the details screen exposes the responsive picture of a clean image', function () {
    $admin = User::factory()->admin()->create();
    $asset = MediaAsset::factory()->withVariants()->create();

    $this->actingAs($admin)->get(route('admin.media.edit', $asset))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('asset.image.width', 640)
            ->where('asset.image.height', 360)
            ->where('asset.image.sources.0.type', 'image/avif')
            ->where('asset.image.sources.1.type', 'image/webp')
            ->where('asset.downloadUrl', route('admin.media.download', $asset))
            ->has('asset.variants', 6)
            ->where('can.delete', true)
        );
});

test('the alternative text is updated with optimistic locking and audited', function () {
    $editor = User::factory()->editor()->create();
    $asset = MediaAsset::factory()->clean()->create();

    $this->actingAs($editor)
        ->put(route('admin.media.update', $asset), ['alt' => '  A red square  ', 'updated_at' => $asset->updated_at?->toIso8601String()])
        ->assertRedirect(route('admin.media.edit', $asset))
        ->assertSessionHasNoErrors();

    expect($asset->refresh()->alt)->toBe('A red square');
    $audit = AuditLog::query()->where('action', AuditAction::MediaUpdated)->sole();
    expect($audit->changes)->toBe(['alt' => ['old' => null, 'new' => 'A red square']]);

    $this->actingAs($editor)
        ->put(route('admin.media.update', $asset), ['alt' => 'Stale', 'updated_at' => '2000-01-01T00:00:00Z'])
        ->assertSessionHasErrors('conflict');

    expect($asset->refresh()->alt)->toBe('A red square');
});

test('editors cannot delete assets', function () {
    $editor = User::factory()->editor()->create();
    $asset = MediaAsset::factory()->withVariants()->create();
    putMediaOriginal($asset);

    $this->actingAs($editor)->delete(route('admin.media.destroy', $asset))->assertForbidden();

    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeTrue();
    Storage::disk('media')->assertExists($asset->path);
    expect(AuditLog::query()->where('action', AuditAction::MediaDeleted)->exists())->toBeFalse();
});

test('an admin deletes the asset with its original and variants', function () {
    $admin = User::factory()->admin()->create();
    $asset = MediaAsset::factory()->withVariants()->create();
    putMediaOriginal($asset);
    Storage::disk('public')->put("media/{$asset->uuid}/abc123-320.webp", 'variant');
    $other = MediaAsset::factory()->withVariants()->create();
    Storage::disk('public')->put("media/{$other->uuid}/abc123-320.webp", 'variant');

    $this->actingAs($admin)
        ->delete(route('admin.media.destroy', $asset))
        ->assertRedirect(route('admin.media.index'));

    expect(MediaAsset::query()->whereKey($asset->id)->exists())->toBeFalse();
    Storage::disk('media')->assertMissing($asset->path);
    Storage::disk('public')->assertMissing("media/{$asset->uuid}/abc123-320.webp");
    Storage::disk('public')->assertExists("media/{$other->uuid}/abc123-320.webp");

    $audit = AuditLog::query()->where('action', AuditAction::MediaDeleted)->sole();
    expect($audit->actor_id)->toBe($admin->id)
        ->and($audit->subject_id)->toBe($asset->id);
});

test('the picker lists only clean images with variants', function () {
    $editor = User::factory()->editor()->create();
    $usable = MediaAsset::factory()->withVariants()->create(['original_name' => 'usable.jpg', 'alt' => 'Usable']);
    MediaAsset::factory()->clean()->create(['original_name' => 'no-variants.jpg']);
    MediaAsset::factory()->create(['original_name' => 'quarantine.jpg', 'variants' => $usable->variants]);
    MediaAsset::factory()->clean()->pdf()->create();

    $this->actingAs($editor)->getJson(route('admin.media.picker'))
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $usable->id)
        ->assertJsonPath('items.0.alt', 'Usable')
        ->assertJsonPath('items.0.width', 640)
        ->assertJsonPath('pagination.total', 1);

    $this->actingAs(User::factory()->create())->getJson(route('admin.media.picker'))->assertForbidden();
});

test('the preview route redirects to the smallest public variant', function () {
    $asset = MediaAsset::factory()->withVariants()->create();

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.media.preview', $asset))
        ->assertRedirect(Storage::disk('public')->url("media/{$asset->uuid}/abc123-320.jpg"));
});
