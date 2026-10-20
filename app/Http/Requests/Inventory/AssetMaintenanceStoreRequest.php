<?php

namespace App\Http\Requests\Inventory;

use App\Enums\MaintenanceType;
use App\Models\AssetMaintenance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetMaintenanceStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', AssetMaintenance::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'maintenance_type' => ['required', Rule::enum(MaintenanceType::class)],
            'reported_issue' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:4000'],
            'reported_date' => ['required', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'service_provider' => ['nullable', 'string', 'max:255'],
            'scheduled_for' => ['nullable', 'date'],
            'repair_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
