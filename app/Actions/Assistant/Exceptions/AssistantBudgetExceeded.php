<?php

namespace App\Actions\Assistant\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The monthly token budget of the assistant is used up.
 */
class AssistantBudgetExceeded extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => __('admin.assistant.errors.budget'), 'reason' => 'budget'], 429);
    }
}
