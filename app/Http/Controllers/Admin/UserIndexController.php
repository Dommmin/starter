<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdminUsersRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class UserIndexController extends Controller
{
    /**
     * Display a searchable, sortable, filtered, paginated list of platform
     * users with their panel role.
     */
    public function index(ListAdminUsersRequest $request): Response
    {
        $listQuery = $request->listQuery();
        $validated = $request->validated();

        $paginator = $listQuery->paginate(User::query(), $validated);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('admin/users/index', [
            ...$listQuery->payload(
                $paginator,
                fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'verified' => $user->email_verified_at !== null,
                    'isSelf' => $actor->is($user),
                    'createdAt' => $user->created_at?->toIso8601String(),
                ],
                $validated,
            ),
            'can' => [
                'create' => $actor->can('create', User::class),
            ],
        ]);
    }
}
