<?php

namespace App\Http\Requests\Admin;

use App\Models\InventoryAlert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResolveInventoryAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alert = $this->route('alert');

        return $alert instanceof InventoryAlert
            && ($this->user()?->can('resolve', $alert) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $alert = $this->route('alert');

                if ($alert instanceof InventoryAlert && $alert->acknowledged_at === null) {
                    $validator->errors()->add('resolution_notes', __('Acknowledge this alert before resolving it, so there is a record of who reviewed it.'));
                }
            },
        ];
    }
}
