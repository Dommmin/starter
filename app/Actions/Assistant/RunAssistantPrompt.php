<?php

namespace App\Actions\Assistant;

use App\Actions\Assistant\Exceptions\AssistantBudgetExceeded;
use App\Actions\Assistant\Exceptions\AssistantUnavailable;
use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AssistantFeature;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Run one assistant suggestion: check the monthly token budget, prompt the
 * structured agent and audit the feature, model and token usage. The prompt
 * and the answer are never logged or audited. The caller checks
 * {@see AssistantFeature::isAvailable()} and the user's ability first.
 */
class RunAssistantPrompt
{
    /**
     * Keep the monthly counter a little longer than a month.
     */
    private const int BUDGET_TTL_SECONDS = 40 * 24 * 3600;

    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  Model  $subject  Record the suggestion is made for; an unsaved instance while creating.
     * @param  User|null  $actor  Null for queued system processes (message classification).
     * @return array<string, mixed> Structured output of the agent.
     *
     * @throws AssistantBudgetExceeded
     * @throws AssistantUnavailable
     */
    public function handle(
        AssistantFeature $feature,
        Agent&HasStructuredOutput $agent,
        string $prompt,
        Model $subject,
        ?User $actor,
    ): array {
        $budgetKey = 'assistant:tokens:'.now()->format('Y-m');

        if ((int) Cache::get($budgetKey, 0) >= (int) config('assistant.monthly_token_budget')) {
            throw new AssistantBudgetExceeded;
        }

        try {
            $response = $agent->prompt($prompt, timeout: (int) config('assistant.timeout'));
        } catch (AiException $exception) {
            Log::warning('Assistant provider request failed.', [
                'feature' => $feature->value,
                'exception' => $exception::class,
            ]);

            throw new AssistantUnavailable(previous: $exception);
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new AssistantUnavailable;
        }

        $tokens = $response->usage->inputTokens + $response->usage->outputTokens;

        Cache::add($budgetKey, 0, self::BUDGET_TTL_SECONDS);
        Cache::increment($budgetKey, $tokens);

        $this->audit->handle(AuditAction::AssistantSuggested, $subject, $actor, [
            'feature' => RecordAuditEvent::change(null, $feature->value),
            'model' => RecordAuditEvent::change(null, $response->meta->model),
            'tokens' => RecordAuditEvent::change(null, $tokens),
        ]);

        return $response->structured;
    }
}
