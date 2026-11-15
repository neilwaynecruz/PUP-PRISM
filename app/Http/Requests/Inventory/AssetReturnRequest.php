<?php

namespace App\Http\Requests\Inventory;

use App\Enums\AssetConditionStatus;
use App\Models\Asset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset
            && ($this->user()?->can('returnToStock', $asset) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'condition_status' => ['nullable', 'string', Rule::enum(AssetConditionStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
