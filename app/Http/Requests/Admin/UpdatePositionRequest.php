<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $position = $this->route('position');

        return $position instanceof Position
            && ($this->user()?->can('update', $position) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Position $position */
        $position = $this->route('position');

        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'title')
                    ->where(fn ($query) => $query->where('department_id', $this->integer('department_id')))
                    ->ignore($position->id),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('positions', 'code')->ignore($position->id)],
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
