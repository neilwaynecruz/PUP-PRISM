<?php

namespace App\Http\Requests\Inventory;

use App\Enums\ProductType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequisitionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.sku' => [
                'required',
                'string',
                'max:64',
                'distinct',
                Rule::exists('products', 'sku')->where(fn ($query) => $query
                    ->where('type', ProductType::Consumable->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'lines.*.qty_requested' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.*.sku.exists' => 'Choose an active consumable product that is available in the catalog.',
            'lines.*.qty_requested.max' => 'Enter a quantity of 1,000,000 or less.',
        ];
    }
}
