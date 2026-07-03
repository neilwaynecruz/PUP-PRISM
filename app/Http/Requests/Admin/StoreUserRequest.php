<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', 'string', Rule::in($this->availableRoles())],
            'position_id' => ['nullable', 'integer', Rule::exists(Position::class, 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower((string) $this->input('email')) : null,
            'position_id' => $this->filled('position_id') ? (int) $this->input('position_id') : null,
            'role' => $this->filled('role') ? trim((string) $this->input('role')) : null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function availableRoles(): array
    {
        return ['Admin', 'Supply Head', 'Property Custodian'];
    }
}
