<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && ($this->user()?->can('update', $department) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'max:255', (new UniqueIgnoringCase('departments', 'name'))->ignore($department->id)],
            'code' => ['nullable', 'string', 'max:50', (new UniqueIgnoringCase('departments', 'code'))->ignore($department->id)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : null,
        ]);
    }
}
