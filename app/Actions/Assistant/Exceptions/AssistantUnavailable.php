<?php

namespace App\Actions\Assistant\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The AI provider failed or timed out; the admin may retry later.
 */
class AssistantUnavailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => __('admin.assistant.errors.unavailable'), 'reason' => 'unavailable'], 503);
    }
}
