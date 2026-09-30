<?php

namespace App\Http\Requests\Admin\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic locking).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'updated_at' => ['required', 'string', 'date'],
            ...$this->profileRules($target->id),
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ];
    }

    public function role(): ?UserRole
    {
        return $this->enum('role', UserRole::class);
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }
}
