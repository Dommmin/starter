<?php

namespace App\Http\Requests\Admin\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * An empty role (converted to null) creates an account without panel access.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ];
    }

    public function role(): ?UserRole
    {
        return $this->enum('role', UserRole::class);
    }
}
