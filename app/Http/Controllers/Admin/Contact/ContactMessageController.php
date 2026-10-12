<?php

namespace App\Http\Controllers\Admin\Contact;

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
use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contact\DestroyContactMessagesRequest;
use App\Http\Requests\Admin\Contact\ListContactMessagesRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Repositories\Contact\ContactMessageRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contact messages in the panel. Authorization: `can` middleware on every
 * route (ContactMessagePolicy).
 */
class ContactMessageController extends Controller
{
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
            ),
        ));
    }

    /**
     * Display one message with its delivery state.
     */
    public function show(Request $request, ContactMessage $contactMessage): Response
    {
        $user = $request->user();

        return Inertia::render('admin/contact/show', new ContactMessageShowData(
            contactMessage: ContactMessageDetailData::fromModel($contactMessage),
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
