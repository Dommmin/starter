<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('verify email notification is localized according to user admin locale preference', function (string $locale, string $expectedSubject, string $expectedAction) {
    $user = User::factory()->unverified()->create([
        'admin_locale' => $locale,
    ]);

    $notification = new VerifyEmail;
    $mailMessage = $notification->toMail($user);

    expect($mailMessage->subject)->toBe($expectedSubject)
        ->and($mailMessage->actionText)->toBe($expectedAction);
})->with([
    ['pl', 'Zweryfikuj swój adres e-mail', 'Zweryfikuj adres e-mail'],
    ['de', 'Bestätigen Sie Ihre E-Mail-Adresse', 'E-Mail-Adresse bestätigen'],
    ['en', 'Verify your email address', 'Verify Email Address'],
]);

test('reset password notification is localized according to user admin locale preference', function (string $locale, string $expectedSubject, string $expectedAction) {
    $user = User::factory()->create([
        'admin_locale' => $locale,
    ]);

    $notification = new ResetPassword('sample-token');
    $mailMessage = $notification->toMail($user);

    expect($mailMessage->subject)->toBe($expectedSubject)
        ->and($mailMessage->actionText)->toBe($expectedAction);
})->with([
    ['pl', 'Zresetuj swoje hasło', 'Zresetuj hasło'],
    ['de', 'Setzen Sie Ihr Passwort zurück', 'Passwort zurücksetzen'],
    ['en', 'Reset your password', 'Reset Password'],
]);

test('new user registration stores active session locale and receives localized verification email', function (string $sessionLocale, string $expectedSubject) {
    Notification::fake();

    $email = "registered-{$sessionLocale}@example.com";

    $response = $this->withSession(['admin_locale' => $sessionLocale])
        ->post(route('register.store'), [
            'name' => 'Localized User',
            'email' => $email,
            'password' => 'Secure-Password-2026!',
            'password_confirmation' => 'Secure-Password-2026!',
        ]);

    $response->assertRedirect();

    $user = User::where('email', $email)->firstOrFail();
    expect($user->admin_locale)->toBe($sessionLocale);

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, $expectedSubject) {
        $mail = $notification->toMail($user);

        return $mail->subject === $expectedSubject;
    });
})->with([
    ['pl', 'Zweryfikuj swój adres e-mail'],
    ['de', 'Bestätigen Sie Ihre E-Mail-Adresse'],
    ['en', 'Verify your email address'],
]);
