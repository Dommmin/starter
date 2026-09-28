<?php

namespace App\Data\Admin\Users;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Editable account fields plus read-only security status. `updatedAt` must
 * be sent back on update for optimistic locking.
 */
#[TypeScript]
class UserFormData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $updatedAt,
        public string $name,
        public string $email,
        public ?UserRole $role,
        public bool $emailVerified,
        public bool $twoFactorEnabled,
    ) {}

    public static function blank(): self
    {
        return new self(
            id: null,
            updatedAt: null,
            name: '',
            email: '',
            role: null,
            emailVerified: false,
            twoFactorEnabled: false,
        );
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            updatedAt: $user->updated_at?->toIso8601String(),
            name: $user->name,
            email: $user->email,
            role: $user->role,
            emailVerified: $user->email_verified_at !== null,
            twoFactorEnabled: $user->two_factor_confirmed_at !== null,
        );
    }
}
