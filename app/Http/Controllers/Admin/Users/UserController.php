<?php

namespace App\Http\Controllers\Admin\Users;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Data\Admin\Users\UserAbilitiesData;
use App\Data\Admin\Users\UserEditorData;
use App\Data\Admin\Users\UserFormData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account management by administrators. Authorization: `can` middleware on
 * every route (UserPolicy); mutations also require a recently confirmed
 * password. Guards for the own role and the last administrator live in the
 * actions.
 */
class UserController extends Controller
{
    /**
     * Show the form for a new account.
     */
    public function create(): Response
    {
        return Inertia::render('admin/users/create', new UserEditorData(
            user: UserFormData::blank(),
            can: new UserAbilitiesData(delete: false, changeRole: true),
        ));
    }

    /**
     * Create the account and queue its invitation email.
     */
    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $user = $createUser->handle(
            $actor,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->role(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.users.created')]);

        return to_route('admin.users.edit', $user);
    }

    /**
     * Show the form for editing the account.
     */
    public function edit(Request $request, User $user): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('admin/users/edit', new UserEditorData(
            user: UserFormData::fromUser($user),
            can: new UserAbilitiesData(
                delete: $actor->can('delete', $user),
                changeRole: $actor->isNot($user),
            ),
        ));
    }

    /**
     * Update the account; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $updateUser->handle(
            $actor,
            $user,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->role(),
            $request->expectedUpdatedAt(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.users.updated')]);

        return to_route('admin.users.edit', $user);
    }

    /**
     * Permanently delete the account (never the own one or the last administrator).
     */
    public function destroy(Request $request, User $user, DeleteUser $deleteUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $deleteUser->handle($actor, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.users.deleted')]);

        return to_route('admin.users.index');
    }
}
