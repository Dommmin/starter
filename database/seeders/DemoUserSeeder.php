<?php

namespace Database\Seeders;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local sample accounts next to the sample administrator: editors, accounts
 * without a panel role, one unverified account, Polish characters and very
 * long names and e-mail addresses, plus short demo logins for every role
 * (`editor@`, `user@`, `mfa@example.com`; the last one is an administrator
 * with confirmed two-factor authentication). Every account uses the
 * local-only password `password`; the TOTP secret and recovery codes below
 * are synthetic and documented in the README. Creation is recorded as
 * `user.created` by the sample administrator. Idempotent: existing e-mail
 * addresses are skipped.
 */
class DemoUserSeeder extends Seeder
{
    /**
     * Synthetic base32 TOTP secret of the two-factor demo account.
     */
    public const string TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXP';

    /**
     * Synthetic recovery codes of the two-factor demo account.
     *
     * @var list<string>
     */
    public const array TWO_FACTOR_RECOVERY_CODES = [
        'demo-recovery-01',
        'demo-recovery-02',
        'demo-recovery-03',
        'demo-recovery-04',
    ];

    /**
     * @var array<string, array{name: string, role: UserRole|null, verified: bool, two_factor?: bool}>
     */
    public const array USERS = [
        'editor@example.com' => ['name' => 'Demo Editor', 'role' => UserRole::Editor, 'verified' => true],
        'user@example.com' => ['name' => 'Demo User', 'role' => null, 'verified' => true],
        'mfa@example.com' => ['name' => 'Demo Admin 2FA', 'role' => UserRole::Admin, 'verified' => true, 'two_factor' => true],
        'klaus.schneider@example.com' => ['name' => 'Klaus Schneider', 'role' => null, 'verified' => true],
        'oliwia.pazdzioch@example.com' => ['name' => 'Oliwia Paździoch', 'role' => UserRole::Editor, 'verified' => true],
        'katarzyna.wisniewska@example.com' => ['name' => 'Katarzyna Wiśniewska-Żółkiewska', 'role' => UserRole::Editor, 'verified' => true],
        'lukasz.slezak@example.com' => ['name' => 'Łukasz Ślęzak', 'role' => UserRole::Editor, 'verified' => true],
        'grzegorz.brzeczyszczykiewicz@example.com' => ['name' => 'Grzegorz Brzęczyszczykiewicz', 'role' => null, 'verified' => true],
        'malgorzata.zdzblo@example.com' => ['name' => 'Małgorzata Źdźbło', 'role' => null, 'verified' => true],
        'jan.kowalski@example.com' => ['name' => 'Jan Kowalski', 'role' => null, 'verified' => true],
        'anna.nowak@example.com' => ['name' => 'Anna Nowak', 'role' => null, 'verified' => true],
        'zofia.cwiklinska@example.com' => ['name' => 'Zofia Ćwiklińska', 'role' => null, 'verified' => true],
        'maximilian.alexander.von.hohenzollern-sigmaringen@a-very-long-subdomain.example.com' => ['name' => 'Maximilian Alexander Friedrich von Hohenzollern-Sigmaringen', 'role' => null, 'verified' => true],
        'emilie.dubois@example.com' => ['name' => 'Émilie Dubois', 'role' => null, 'verified' => true],
        'li.na@example.com' => ['name' => 'Li Na', 'role' => null, 'verified' => true],
        'piotr.zak@example.com' => ['name' => 'Piotr Żak', 'role' => null, 'verified' => false],
    ];

    public function run(RecordAuditEvent $audit): void
    {
        $administrator = User::query()->where('email', DemoContent::USER_EMAIL)->first();

        foreach (self::USERS as $email => $definition) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            DB::transaction(function () use ($email, $definition, $administrator, $audit): void {
                $user = new User;
                $user->forceFill([
                    'name' => $definition['name'],
                    'email' => $email,
                    'password' => 'password',
                    'email_verified_at' => $definition['verified'] ? now() : null,
                    'role' => $definition['role'],
                    ...(($definition['two_factor'] ?? false) ? [
                        'two_factor_secret' => encrypt(self::TWO_FACTOR_SECRET),
                        'two_factor_recovery_codes' => encrypt((string) json_encode(self::TWO_FACTOR_RECOVERY_CODES)),
                        'two_factor_confirmed_at' => now(),
                    ] : []),
                ])->save();

                $audit->handle(AuditAction::UserCreated, $user, $administrator, [
                    'name' => RecordAuditEvent::redacted(),
                    'email' => RecordAuditEvent::redacted(),
                    'role' => RecordAuditEvent::change(null, $definition['role']?->value),
                ]);
            });
        }
    }
}
