<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Invitation to an account created by an administrator. The link is a
 * regular password reset link, created when the mail is built (the token is
 * never stored in the queued payload); the administrator never knows the
 * password. Sent after the creating transaction commits.
 */
class AccountInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 30;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->preferredLocale() ?: app()->getLocale();
        $token = Password::broker()->createToken($notifiable);

        $url = app(LocalizedUrlGenerator::class)->url('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], $locale);

        return (new MailMessage)
            ->subject(__('auth.emails.invitation.subject', ['app' => config('app.name')], $locale))
            ->line(__('auth.emails.invitation.line_1', ['app' => config('app.name')], $locale))
            ->action(__('auth.emails.invitation.action', [], $locale), $url)
            ->line(__('auth.emails.invitation.line_2', [
                'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ], $locale))
            ->line(__('auth.emails.invitation.line_3', [], $locale));
    }
}
