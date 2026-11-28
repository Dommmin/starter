<?php

namespace App\Http\Controllers\Admin\Assistant;

use App\Actions\Assistant\RunAssistantPrompt;
use App\Ai\Agents\ContactReplyAgent;
use App\Enums\AssistantFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assistant\GenerateContactReplyRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Repositories\Settings\SiteSettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Drafts a reply proposal (subject + body) for a contact message; nothing is
 * sent or saved automatically.
 */
class ContactReplySuggestionController extends Controller
{
    public function __invoke(
        GenerateContactReplyRequest $request,
        RunAssistantPrompt $assistant,
        SiteSettingsRepository $siteSettings,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $contactMessage = ContactMessage::query()->findOrFail($request->integer('contact_message_id'));
        $settings = $siteSettings->current($contactMessage->locale);
        $siteName = $settings->name !== '' ? $settings->name : (string) config('app.name');

        $promptLines = [
            "Sender name: {$contactMessage->name}",
            "Inbound message:\n{$contactMessage->message}",
        ];

        $guidance = $request->input('guidance');
        if (is_string($guidance) && trim($guidance) !== '') {
            $promptLines[] = 'Admin guidance: '.trim($guidance);
        }

        $output = $assistant->handle(
            AssistantFeature::ReplyDraft,
            new ContactReplyAgent($contactMessage->locale, $siteName),
            implode("\n\n", $promptLines),
            $contactMessage,
            $user,
        );

        return response()->json([
            'subject' => Str::limit(trim((string) ($output['subject'] ?? '')), 180, ''),
            'body' => trim((string) ($output['body'] ?? '')),
        ]);
    }
}
