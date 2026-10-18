<?php

namespace App\Http\Controllers\Admin\Search;

use App\Actions\Admin\SearchAdminRecords;
use App\Data\Admin\Search\AdminSearchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Search\SearchAdminRecordsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * JSON search of the admin command palette (⌘K).
 */
class AdminSearchController extends Controller
{
    public function __invoke(SearchAdminRecordsRequest $request, SearchAdminRecords $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $term = $request->term();

        return response()->json(new AdminSearchData(
            query: $term,
            results: $search->handle($user, $term),
        ));
    }
}
