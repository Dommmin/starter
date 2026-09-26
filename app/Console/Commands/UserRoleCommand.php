<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:user-role {email : The e-mail address of the user} {role? : The role to assign (admin|editor|none)}')]
#[Description('Assign or revoke the administration panel role of a user')]
class UserRoleCommand extends Command
{
    /**
     * The value that revokes the current role.
     */
    private const string NO_ROLE = 'none';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $roleInput = strtolower((string) ($this->argument('role') ?? self::NO_ROLE));

        $allowedRoles = [...array_column(UserRole::cases(), 'value'), self::NO_ROLE];

        if (! in_array($roleInput, $allowedRoles, true)) {
            $this->error(sprintf('Unknown role "%s". Allowed values: %s.', $roleInput, implode(', ', $allowedRoles)));

            return self::INVALID;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error(sprintf('No user found with e-mail "%s".', $email));

            return self::FAILURE;
        }

        $role = $roleInput === self::NO_ROLE ? null : UserRole::from($roleInput);

        $user->assignRole($role);

        $this->info($role === null
            ? sprintf('Role revoked from %s.', $user->email)
            : sprintf('Role "%s" assigned to %s.', $role->value, $user->email));

        return self::SUCCESS;
    }
}
