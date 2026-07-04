<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Position::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'title')->where(fn ($query) => $query->where('department_id', $this->integer('department_id'))),
            ],
            'code' => ['required', 'string', 'max:50', 'unique:positions,code'],
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

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $departmentId = $this->integer('department_id');

                if ($departmentId === 0) {
                    return;
                }

                $department = Department::query()->find($departmentId);

                if ($department instanceof Department && ! $department->is_active) {
                    $validator->errors()->add('department_id', __('Positions cannot be assigned to an inactive department.'));
                }
            },
        ];
    }
}
