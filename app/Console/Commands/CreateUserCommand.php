<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * First administrator (or any account) on a server without the panel or
 * outgoing mail. The password is asked twice without echo, never taken as
 * an argument (shell history), and validated like a password change.
 * Without --password the account gets the e-mail invitation instead.
 */
#[Signature('app:create-user
    {email : The e-mail address of the new user}
    {--name= : Display name (asked when omitted)}
    {--role=admin : Panel role (admin|editor|viewer|none)}
    {--password : Ask for a password instead of sending the e-mail invitation}')]
#[Description('Create a user account from the console')]
class CreateUserCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * The value that creates an account without a panel role.
     */
    private const string NO_ROLE = 'none';

    /**
     * Execute the console command.
     */
    public function handle(CreateUser $createUser): int
    {
        $roleOption = $this->option('role');
        $roleInput = strtolower(is_string($roleOption) ? $roleOption : self::NO_ROLE);
        $allowedRoles = [...array_column(UserRole::cases(), 'value'), self::NO_ROLE];

        if (! in_array($roleInput, $allowedRoles, true)) {
            $this->error(sprintf('Unknown role "%s". Allowed values: %s.', $roleInput, implode(', ', $allowedRoles)));

            return self::INVALID;
        }

        $emailArgument = $this->argument('email');
        $nameOption = $this->option('name');
        $input = [
            'email' => is_string($emailArgument) ? trim($emailArgument) : '',
            'name' => is_string($nameOption) ? trim($nameOption) : trim((string) $this->ask('Name')),
        ];
        $rules = $this->profileRules();

        if ($this->option('password')) {
            $input['password'] = (string) $this->secret('Password');
            $input['password_confirmation'] = (string) $this->secret('Repeat the password');
            $rules['password'] = $this->passwordRules();
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $role = $roleInput === self::NO_ROLE ? null : UserRole::from($roleInput);
        $user = $createUser->handle(null, $input['name'], $input['email'], $role, $input['password'] ?? null);

        $this->info(sprintf('User %s created%s.', $user->email, $role === null ? '' : sprintf(' with the "%s" role', $role->value)));
        $this->line(isset($input['password'])
            ? 'Sign in with the password you entered.'
            : 'Invitation with a set-password link queued.');

        return self::SUCCESS;
    }
}
