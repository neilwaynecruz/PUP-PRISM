<?php

namespace App\Http\Requests\Inventory;

use App\Enums\MaintenanceStatus;
use App\Models\AssetMaintenance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetMaintenanceTransitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $maintenance = $this->route('assetMaintenance');

        return $maintenance instanceof AssetMaintenance
            && ($this->user()?->can('transition', $maintenance) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'service_provider' => ['nullable', 'string', 'max:255'],
            'scheduled_for' => ['nullable', 'date'],
            'repair_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'findings' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
