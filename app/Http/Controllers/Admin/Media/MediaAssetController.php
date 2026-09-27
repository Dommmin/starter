<?php

namespace App\Http\Controllers\Admin\Media;

use App\Actions\Media\DeleteMediaAsset;
use App\Actions\Media\StoreMediaAsset;
use App\Actions\Media\UpdateMediaAsset;
use App\Data\Admin\Media\MediaAssetAbilitiesData;
use App\Data\Admin\Media\MediaAssetDetailData;
use App\Data\Admin\Media\MediaAssetEditorData;
use App\Data\Admin\Media\MediaAssetIndexData;
use App\Data\Admin\Media\MediaAssetListFiltersData;
use App\Data\Admin\Media\MediaAssetListItemData;
use App\Data\Admin\Media\MediaPickerData;
use App\Data\Admin\Media\MediaPickerItemData;
use App\Data\Admin\Media\MediaUploadRulesData;
use App\Data\Listing\ListPaginationData;
use App\Data\Media\MediaImageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Media\ListMediaAssetsRequest;
use App\Http\Requests\Admin\Media\MediaPickerRequest;
use App\Http\Requests\Admin\Media\StoreMediaAssetRequest;
use App\Http\Requests\Admin\Media\UpdateMediaAssetRequest;
use App\Models\MediaAsset;
use App\Models\User;
use App\Repositories\Media\MediaAssetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administrative DAM. Authorization: `can` middleware on every route
 * (MediaAssetPolicy), re-checked by the store/update requests. Originals are
 * served only by `download`, and only for clean assets.
 */
class MediaAssetController extends Controller
{
    public function __construct(private readonly MediaAssetRepository $media) {}

    /**
     * Display the searchable, filterable, paginated media list.
     */
    public function index(ListMediaAssetsRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();

        $paginator = $listQuery->paginate($this->media->adminListQuery(), $validated);

        $items = [];
        foreach ($paginator->items() as $asset) {
            $items[] = MediaAssetListItemData::fromModel($asset);
        }

        return Inertia::render('admin/media/index', new MediaAssetIndexData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
            filters: MediaAssetListFiltersData::from($listQuery->filtersPayload($validated)),
            can: $this->abilities($request, null),
            upload: MediaUploadRulesData::fromConfig(),
        ));
    }

    /**
     * Store one upload in quarantine and queue its malware scan.
     */
    public function store(StoreMediaAssetRequest $request, StoreMediaAsset $storeMediaAsset): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $storeMediaAsset->handle(
            $user,
            $request->uploadedFile(),
            $request->detectedMime(),
            $request->sanitizedOriginalName(),
        );

        return back(fallback: route('admin.media.index'));
    }

    /**
     * Show the asset details and the alternative text form.
     */
    public function edit(Request $request, MediaAsset $mediaAsset): Response
    {
        return Inertia::render('admin/media/edit', new MediaAssetEditorData(
            asset: MediaAssetDetailData::fromModel($this->media->forEditor($mediaAsset)),
            can: $this->abilities($request, $mediaAsset),
        ));
    }

    /**
     * Update the alternative text; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateMediaAssetRequest $request, MediaAsset $mediaAsset, UpdateMediaAsset $updateMediaAsset): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateMediaAsset->handle($mediaAsset, $user, $request->alt(), $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.media.updated')]);

        return to_route('admin.media.edit', $mediaAsset);
    }

    /**
     * Permanently delete the asset with its files (administrators only).
     */
    public function destroy(Request $request, MediaAsset $mediaAsset, DeleteMediaAsset $deleteMediaAsset): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleteMediaAsset->handle($mediaAsset, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.media.deleted')]);

        return to_route('admin.media.index');
    }

    /**
     * Download the private original of a clean asset as an attachment.
     * Quarantined and rejected files are never served.
     */
    public function download(MediaAsset $mediaAsset): StreamedResponse
    {
        abort_unless($mediaAsset->hasPassedScan(), 404);

        $disk = Storage::disk($mediaAsset->disk);
        abort_unless($disk->exists($mediaAsset->path), 404);

        return $disk->download($mediaAsset->path, $mediaAsset->original_name, [
            'Content-Type' => $mediaAsset->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Redirect to the smallest public variant; used by the rich text editor
     * to preview an image node that only stores the media id.
     */
    public function preview(MediaAsset $mediaAsset): RedirectResponse
    {
        $url = MediaImageData::thumbnailUrl($mediaAsset);

        abort_if($url === null, 404);

        return redirect()->away($url);
    }

    /**
     * Clean images for the rich text image picker (JSON, not an Inertia page).
     */
    public function picker(MediaPickerRequest $request): JsonResponse
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();
        $paginator = $listQuery->paginate($this->media->pickerQuery(), $validated);

        $items = [];
        foreach ($paginator->items() as $asset) {
            $item = MediaPickerItemData::fromModel($asset);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return response()->json(new MediaPickerData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
        ));
    }

    private function abilities(Request $request, ?MediaAsset $asset): MediaAssetAbilitiesData
    {
        $user = $request->user();

        return new MediaAssetAbilitiesData(
            create: $user?->can('create', MediaAsset::class) ?? false,
            update: $asset !== null
                ? ($user?->can('update', $asset) ?? false)
                : ($user?->canAccessAdminPanel() ?? false),
            delete: $asset !== null
                ? ($user?->can('delete', $asset) ?? false)
                : ($user?->isAdmin() ?? false),
        );
    }
}
