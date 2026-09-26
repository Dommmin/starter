<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $admin_locale
 * @property UserRole|null $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'admin_locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'two_factor_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'admin_locale' => 'string',
            'role' => UserRole::class,
        ];
    }

    /**
     * Get the user's preferred locale for notifications.
     */
    public function preferredLocale(): ?string
    {
        return $this->admin_locale ?: config('localization.admin_default', 'en');
    }

    /**
     * Determine whether the user has the given role.
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Determine whether the user has administrator privileges.
     *
     * The local environment grants administrator privileges to every user.
     */
    public function isAdmin(): bool
    {
        return app()->environment('local') || $this->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user may access the administration panel.
     *
     * The local environment grants panel access to every user.
     */
    public function canAccessAdminPanel(): bool
    {
        return app()->environment('local')
            || $this->hasRole(UserRole::Admin)
            || $this->hasRole(UserRole::Editor);
    }

    /**
     * Assign the given role, or revoke the current one when null, and persist it.
     *
     * The role is intentionally not mass assignable.
     */
    public function assignRole(?UserRole $role): void
    {
        $this->forceFill(['role' => $role])->save();
    }

    /**
     * Determine whether two-factor authentication is enabled.
     *
     * @return Attribute<bool, never>
     */
    protected function twoFactorEnabled(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->hasEnabledTwoFactorAuthentication(),
        );
    }
}
