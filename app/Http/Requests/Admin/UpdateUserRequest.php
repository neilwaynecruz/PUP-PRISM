<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        /** @var User $managedUser */
        $managedUser = $this->route('managedUser');

        return $this->user()?->can('update', $managedUser) ?? false;
    }

    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('managedUser');

        return [
            ...$this->profileRules($managedUser->id),
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
