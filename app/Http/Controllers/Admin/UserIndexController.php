<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdminUsersRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class UserIndexController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Display a searchable, sortable, filtered, paginated list of platform users.
     */
    public function index(ListAdminUsersRequest $request): Response
    {
        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $verified = (string) ($validated['verified'] ?? 'all');
        $sort = (string) ($validated['sort'] ?? 'created_at');

        /** @var 'asc'|'desc' $direction */
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = User::query();

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($verified === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verified === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        $paginator = $query
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $paginator->getCollection()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'verified' => $user->email_verified_at !== null,
                    'createdAt' => $user->created_at?->toIso8601String(),
                ])
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'totalPages' => max($paginator->lastPage(), 1),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'filters' => [
                'search' => $search,
                'verified' => $verified,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }
}
