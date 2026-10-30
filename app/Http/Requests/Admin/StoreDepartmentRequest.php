<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Department::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new UniqueIgnoringCase('departments', 'name')],
            'code' => ['nullable', 'string', 'max:50', new UniqueIgnoringCase('departments', 'code')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'code' => $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : null,
        ]);
    }
}
