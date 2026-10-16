<?php

namespace App\Http\Controllers\Admin\Contact;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Contact\DeleteContactMessages;
use App\Actions\Contact\RetryContactMessage;
use App\Data\Admin\Contact\ContactMessageAbilitiesData;
use App\Data\Admin\Contact\ContactMessageDetailData;
use App\Data\Admin\Contact\ContactMessageIndexData;
use App\Data\Admin\Contact\ContactMessageListAbilitiesData;
use App\Data\Admin\Contact\ContactMessageListFiltersData;
use App\Data\Admin\Contact\ContactMessageListItemData;
use App\Data\Admin\Contact\ContactMessageShowData;
use App\Data\Listing\ListPaginationData;
use App\Enums\AuditAction;
use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contact\DestroyContactMessagesRequest;
use App\Http\Requests\Admin\Contact\ListContactMessagesRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Repositories\Contact\ContactMessageRepository;
use App\Services\Localization\LocalizationConfig;
use App\Support\Csv\CsvCell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contact messages in the panel. Authorization: `can` middleware on every
 * route (ContactMessagePolicy).
 */
class ContactMessageController extends Controller
{
    /** Default row limit of the CSV export (`exports.contact.max_rows`). */
    private const int EXPORT_MAX_ROWS = 5000;

    public function __construct(private readonly ContactMessageRepository $contactMessages) {}

    /**
     * Display the searchable, filterable, paginated list, newest first.
     */
    public function index(ListContactMessagesRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();

        $paginator = $listQuery->paginate($this->contactMessages->adminListQuery(), $validated);

        $items = [];
        foreach ($paginator->items() as $contactMessage) {
            $items[] = ContactMessageListItemData::fromModel($contactMessage);
        }

        return Inertia::render('admin/contact/index', new ContactMessageIndexData(
            items: $items,
            pagination: ListPaginationData::from($listQuery->paginationPayload($paginator)),
            filters: ContactMessageListFiltersData::from($listQuery->filtersPayload($validated)),
            // List-level hint like the other modules; each message is
            // authorized again when the selection is deleted.
            can: new ContactMessageListAbilitiesData(
                delete: $request->user()?->isAdmin() ?? false,
                export: $request->user()?->can('export', ContactMessage::class) ?? false,
            ),
            exportMaxRows: config()->integer('exports.contact.max_rows', self::EXPORT_MAX_ROWS),
        ));
    }

    /**
     * Download the filtered list as CSV (administrators only), newest first,
     * at most `exports.contact.max_rows` rows; the export is audited without
     * the search text.
     */
    public function export(ListContactMessagesRequest $request, RecordAuditEvent $recordAuditEvent): HttpResponse|StreamedResponse
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();
        $maxRows = config()->integer('exports.contact.max_rows', self::EXPORT_MAX_ROWS);

        $query = $listQuery->apply($this->contactMessages->adminListQuery()->addSelect(['message', 'contact_form_id', 'fields'])->with('form.fields'), $validated);
        $rows = (clone $query)->reorder()->count();

        if ($rows > $maxRows) {
            return response(__('admin.contact.exportTooLarge', ['max' => $maxRows]), 422)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $filters = $listQuery->filtersPayload($validated);
        $recordAuditEvent->handle(AuditAction::ResourceExported, new ContactMessage, $request->user(), [
            'module' => RecordAuditEvent::change(null, 'contact'),
            'rows' => RecordAuditEvent::change(null, $rows),
            'filters' => RecordAuditEvent::change(null, Arr::except($filters, ['search'])),
            ...($filters['search'] === '' ? [] : ['search' => RecordAuditEvent::redacted()]),
        ]);

        return response()->streamDownload(function () use ($query, $maxRows): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, [
                __('admin.contact.columnId'),
                __('admin.contact.fields.createdAt'),
                __('admin.contact.fields.name'),
                __('admin.contact.fields.email'),
                __('admin.contact.fields.locale'),
                __('admin.contact.fields.status'),
                __('admin.contact.fields.message'),
                __('admin.contact.fields.form'),
                __('admin.contact.fields.extraFields'),
            ], escape: '');
            $locale = app()->getLocale();
            $fallback = app(LocalizationConfig::class)->getPublicFallback();

            foreach ($query->lazy(500)->take($maxRows) as $contactMessage) {
                /** @var ContactMessage $contactMessage */
                fputcsv($output, [
                    $contactMessage->id,
                    CsvCell::safe($contactMessage->created_at?->toIso8601String()),
                    CsvCell::safe($contactMessage->name),
                    CsvCell::safe($contactMessage->email),
                    CsvCell::safe($contactMessage->locale),
                    CsvCell::safe(__("admin.contact.statuses.{$contactMessage->status->value}")),
                    CsvCell::safe($contactMessage->message),
                    CsvCell::safe($contactMessage->form?->key),
                    CsvCell::safe(implode('; ', array_map(
                        fn (array $row): string => "{$row['label']}: {$row['value']}",
                        $contactMessage->extraFieldRows($locale, $fallback),
                    ))),
                ], escape: '');
            }

            fclose($output);
        }, __('admin.contact.exportFilename', ['date' => now()->format('Y-m-d')]), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Display one message with its delivery state.
     */
    public function show(Request $request, ContactMessage $contactMessage): Response
    {
        $user = $request->user();

        return Inertia::render('admin/contact/show', new ContactMessageShowData(
            contactMessage: ContactMessageDetailData::fromModel(
                $contactMessage->load('form.fields'),
                app()->getLocale(),
                app(LocalizationConfig::class)->getPublicFallback(),
            ),
            can: new ContactMessageAbilitiesData(
                retry: $contactMessage->status === ContactMessageStatus::Failed
                    && ($user?->can('retry', $contactMessage) ?? false),
                delete: $user?->can('delete', $contactMessage) ?? false,
            ),
        ));
    }

    /**
     * Queue another delivery attempt of a failed message (administrators only).
     */
    public function retry(ContactMessage $contactMessage, RetryContactMessage $retryContactMessage): RedirectResponse
    {
        $queued = $contactMessage->status === ContactMessageStatus::Failed
            && $retryContactMessage->handle($contactMessage);

        Inertia::flash('toast', $queued
            ? ['type' => 'success', 'message' => __('admin.contact.retryQueued')]
            : ['type' => 'error', 'message' => __('admin.contact.retryNotAllowed')]);

        return to_route('admin.contact.show', $contactMessage);
    }

    /**
     * Permanently delete the message (administrators only).
     */
    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.contact.deleted')]);

        return to_route('admin.contact.index');
    }

    /**
     * Permanently delete the selected messages of one list page (administrators
     * only; all or nothing).
     */
    public function destroyMany(DestroyContactMessagesRequest $request, DeleteContactMessages $deleteContactMessages): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deleted = $deleteContactMessages->handle($user, $request->ids());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.contact.deletedMany', ['count' => $deleted])]);

        return back();
    }
}
