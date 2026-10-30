<?php

namespace App\Http\Requests\Admin;

use App\Models\InventoryAlert;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignInventoryAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alert = $this->route('alert');

        return $alert instanceof InventoryAlert
            && ($this->user()?->can('assign', $alert) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('assigned_to')) {
                    return;
                }

                if (! $this->assignee()->hasAnyRole(['Admin', 'Supply Head'])) {
                    $validator->errors()->add('assigned_to', __('Alerts can only be assigned to an active Admin or Supply Head.'));
                }
            },
        ];
    }

    public function assignee(): User
    {
        return User::query()->whereKey($this->integer('assigned_to'))->firstOrFail();
    }
}
