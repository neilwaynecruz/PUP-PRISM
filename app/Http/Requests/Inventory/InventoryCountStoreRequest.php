<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryCountSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryCountStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryCountSession::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counting_date' => ['required', 'date'],
            'scope' => ['required', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => ['integer', 'distinct', 'exists:assets,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->input('product_ids', []) === [] && $this->input('asset_ids', []) === []) {
                    $validator->errors()->add('product_ids', __('Select at least one product or asset to count.'));
                }
            },
        ];
    }
}
